<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../ai/DocumentProcessorAI.php';

use ArdhiYetu\AI\DocumentProcessorAI;

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

try {
    // Check for file upload
    if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No valid file uploaded');
    }
    
    $documentType = $_POST['document_type'] ?? 'title_deed';
    $allowedTypes = ['title_deed', 'id_card', 'survey_map', 'legal_contract'];
    
    if (!in_array($documentType, $allowedTypes)) {
        throw new Exception('Invalid document type');
    }
    
    // Create upload directory if not exists
    $uploadDir = __DIR__ . '/../uploads/ai_documents/' . date('Y/m/d');
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    // Generate unique filename
    $filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9\.\-]/', '_', $_FILES['document']['name']);
    $filePath = $uploadDir . '/' . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($_FILES['document']['tmp_name'], $filePath)) {
        throw new Exception('Failed to save uploaded file');
    }
    
    // Process document
    $ai = new DocumentProcessorAI();
    $startTime = microtime(true);
    
    $result = $ai->processDocument($filePath, $documentType);
    
    $processingTime = round((microtime(true) - $startTime) * 1000); // ms
    
    // Save results
    $stmt = $conn->prepare("
        INSERT INTO document_analysis_results 
        (document_type, extracted_data, verification_results, anomalies, 
         confidence_score, processed_by, processing_time_ms)
        VALUES (?, ?, ?, ?, ?, 'ai', ?)
    ");
    
    $stmt->bind_param(
        'ssssdi',
        $documentType,
        json_encode($result['extracted_data']),
        json_encode($result['verification_results']),
        json_encode($result['anomalies']),
        $result['confidence_score'],
        $processingTime
    );
    $stmt->execute();
    $analysisId = $stmt->insert_id;
    
    // Generate structured data if needed
    $structuredData = [];
    if (isset($_POST['generate_structured']) && $_POST['generate_structured'] === 'true') {
        $structuredData = $ai->generateStructuredData($result, 'land_registration');
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'analysis_id' => $analysisId,
        'document_type' => $documentType,
        'results' => $result,
        'structured_data' => $structuredData,
        'file_path' => str_replace(__DIR__ . '/../', '', $filePath),
        'processing_time_ms' => $processingTime,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Document processing failed',
        'message' => $e->getMessage()
    ]);
}
?>