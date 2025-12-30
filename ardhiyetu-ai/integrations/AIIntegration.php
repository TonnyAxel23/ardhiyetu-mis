<?php
namespace ArdhiYetu\Integrations;

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../ai/LandValuationAI.php';
require_once __DIR__ . '/../ai/DocumentProcessorAI.php';
require_once __DIR__ . '/../ai/FraudDetectionAI.php';
require_once __DIR__ . '/../ai/LandRecommendationAI.php';

use ArdhiYetu\AI\LandValuationAI;
use ArdhiYetu\AI\DocumentProcessorAI;
use ArdhiYetu\AI\FraudDetectionAI;
use ArdhiYetu\AI\LandRecommendationAI;

class AIIntegration {
    private $conn;
    private $valuationAI;
    private $documentAI;
    private $fraudAI;
    private $recommendationAI;
    
    public function __construct($conn = null) {
        $this->conn = $conn ?? $GLOBALS['conn'];
        $this->initializeAIServices();
    }
    
    private function initializeAIServices(): void {
        $this->valuationAI = new LandValuationAI($this->conn);
        $this->documentAI = new DocumentProcessorAI();
        $this->fraudAI = new FraudDetectionAI($this->conn);
        $this->recommendationAI = new LandRecommendationAI($this->conn);
    }
    
    /**
     * Process new land registration with AI
     */
    public function processLandRegistration(array $landData): array {
        $results = [];
        
        // 1. AI Valuation
        $results['valuation'] = $this->valuationAI->estimateValue($landData);
        
        // 2. Document processing if documents uploaded
        if (!empty($landData['documents'])) {
            $results['documents'] = [];
            foreach ($landData['documents'] as $document) {
                $docResult = $this->documentAI->processDocument(
                    $document['path'],
                    $document['type']
                );
                $results['documents'][] = $docResult;
            }
        }
        
        // 3. Fraud risk assessment
        $fraudData = [
            'location' => $landData['location'],
            'size' => $landData['size'],
            'price' => $results['valuation']['estimated_value'] ?? 0,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        $results['fraud_risk'] = $this->fraudAI->analyzeTransaction($fraudData);
        
        // 4. Generate recommendations for similar lands
        if (isset($_SESSION['user_id'])) {
            $results['recommendations'] = $this->recommendationAI->getRecommendations(
                $_SESSION['user_id'],
                [
                    'location' => $landData['location'],
                    'land_type' => $landData['land_type'] ?? 'agricultural',
                    'min_size' => $landData['size'] * 0.8,
                    'max_size' => $landData['size'] * 1.2
                ]
            );
        }
        
        // Log AI processing
        $this->logAIProcessing('land_registration', $landData, $results);
        
        return $results;
    }
    
    /**
     * Process land transfer with AI
     */
    public function processLandTransfer(array $transferData): array {
        $results = [];
        
        // 1. Fraud detection
        $results['fraud_analysis'] = $this->fraudAI->analyzeTransaction($transferData);
        
        // 2. Document verification
        if (!empty($transferData['documents'])) {
            $results['document_analysis'] = [];
            foreach ($transferData['documents'] as $document) {
                $docResult = $this->documentAI->processDocument(
                    $document['path'],
                    'legal_contract'
                );
                $results['document_analysis'][] = $docResult;
                
                // Check for inconsistencies
                if (!empty($docResult['anomalies'])) {
                    $results['fraud_analysis']['risk_score'] = min(
                        1.0,
                        $results['fraud_analysis']['risk_score'] + 0.2
                    );
                }
            }
        }
        
        // 3. Price validation
        $landData = $this->getLandData($transferData['record_id']);
        if ($landData) {
            $valuation = $this->valuationAI->estimateValue($landData);
            $priceRatio = $transferData['price'] / $valuation['estimated_value'];
            
            $results['price_validation'] = [
                'market_value' => $valuation['estimated_value'],
                'transfer_price' => $transferData['price'],
                'ratio' => $priceRatio,
                'is_fair' => $priceRatio >= 0.8 && $priceRatio <= 1.2
            ];
            
            if (!$results['price_validation']['is_fair']) {
                $results['fraud_analysis']['risk_score'] = min(
                    1.0,
                    $results['fraud_analysis']['risk_score'] + 0.3
                );
            }
        }
        
        // 4. Update risk level
        $results['fraud_analysis']['risk_level'] = $this->fraudAI->getRiskLevel(
            $results['fraud_analysis']['risk_score']
        );
        
        // Log processing
        $this->logAIProcessing('land_transfer', $transferData, $results);
        
        return $results;
    }
    
    /**
     * Get AI-powered insights for dashboard
     */
    public function getDashboardInsights(int $userId): array {
        $insights = [];
        
        // 1. Land valuation trends
        $insights['valuation_trends'] = $this->getValuationTrends($userId);
        
        // 2. Fraud risk summary
        $insights['fraud_summary'] = $this->getFraudSummary($userId);
        
        // 3. Personalized recommendations
        $insights['recommendations'] = $this->recommendationAI->getRecommendations($userId);
        
        // 4. Market insights
        $insights['market_insights'] = $this->getMarketInsights($userId);
        
        // 5. AI predictions accuracy
        $insights['ai_accuracy'] = $this->getAIPredictionAccuracy();
        
        return $insights;
    }
    
    private function getLandData(int $recordId): ?array {
        $sql = "SELECT * FROM land_records WHERE record_id = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $recordId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        return mysqli_fetch_assoc($result);
    }
    
    private function getValuationTrends(int $userId): array {
        $sql = "SELECT 
                    DATE(created_at) as date,
                    AVG(JSON_EXTRACT(output_data, '$.estimated_value')) as avg_value,
                    COUNT(*) as valuation_count
                FROM ai_predictions
                WHERE user_id = ?
                AND prediction_type = 'valuation'
                AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY DATE(created_at)
                ORDER BY date DESC
                LIMIT 10";
        
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $trends = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $trends[] = $row;
        }
        
        return $trends;
    }
    
    private function getFraudSummary(int $userId): array {
        $sql = "SELECT 
                    risk_level,
                    COUNT(*) as count,
                    AVG(risk_score) as avg_score
                FROM fraud_analysis_logs fal
                JOIN ownership_transfers ot ON fal.transaction_id = ot.transfer_id
                WHERE ot.from_user_id = ?
                AND fal.created_at > DATE_SUB(NOW(), INTERVAL 90 DAY)
                GROUP BY risk_level
                ORDER BY FIELD(risk_level, 'CRITICAL', 'HIGH', 'MEDIUM', 'LOW', 'MINIMAL')";
        
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $summary = [
            'total_transactions' => 0,
            'high_risk_count' => 0,
            'avg_risk_score' => 0
        ];
        
        while ($row = mysqli_fetch_assoc($result)) {
            $summary['total_transactions'] += $row['count'];
            if (in_array($row['risk_level'], ['CRITICAL', 'HIGH'])) {
                $summary['high_risk_count'] += $row['count'];
            }
        }
        
        return $summary;
    }
    
    private function getMarketInsights(int $userId): array {
        // Get user's location preferences
        $sql = "SELECT location FROM land_records WHERE owner_id = ? GROUP BY location LIMIT 3";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $locations = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $locations[] = $row['location'];
        }
        
        if (empty($locations)) {
            return [];
        }
        
        // Get market trends for these locations
        $insights = [];
        foreach ($locations as $location) {
            $sql = "SELECT 
                        AVG(price_per_acre) as avg_price,
                        COUNT(*) as transaction_count,
                        MAX(transaction_date) as latest_transaction
                    FROM historical_transactions
                    WHERE location LIKE ?
                    AND transaction_date > DATE_SUB(NOW(), INTERVAL 6 MONTH)";
            
            $stmt = mysqli_prepare($this->conn, $sql);
            $locationPattern = "%$location%";
            mysqli_stmt_bind_param($stmt, 's', $locationPattern);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $data = mysqli_fetch_assoc($result);
            
            if ($data && $data['transaction_count'] > 0) {
                $insights[$location] = [
                    'avg_price_per_acre' => round($data['avg_price'], 2),
                    'transaction_volume' => $data['transaction_count'],
                    'market_activity' => $data['latest_transaction'] > date('Y-m-d', strtotime('-30 days')) 
                        ? 'Active' : 'Slow'
                ];
            }
        }
        
        return $insights;
    }
    
    private function getAIPredictionAccuracy(): array {
        $sql = "SELECT 
                    prediction_type,
                    COUNT(*) as total_predictions,
                    AVG(confidence_score) as avg_confidence,
                    MIN(confidence_score) as min_confidence,
                    MAX(confidence_score) as max_confidence
                FROM ai_predictions
                WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY prediction_type";
        
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $accuracy = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $accuracy[$row['prediction_type']] = $row;
        }
        
        return $accuracy;
    }
    
    private function logAIProcessing(string $processType, array $input, array $results): void {
        $sql = "INSERT INTO ai_processing_logs 
                (process_type, input_data, output_data, user_id, created_at)
                VALUES (?, ?, ?, ?, NOW())";
        
        $stmt = mysqli_prepare($this->conn, $sql);
        $inputJson = json_encode($input);
        $outputJson = json_encode($results);
        $userId = $_SESSION['user_id'] ?? null;
        
        mysqli_stmt_bind_param($stmt, 'sssi', 
            $processType, $inputJson, $outputJson, $userId);
        mysqli_stmt_execute($stmt);
    }
    
    /**
     * Batch process documents with AI
     */
    public function batchProcessDocuments(array $documentPaths, string $documentType = 'title_deed'): array {
        $results = [];
        $total = count($documentPaths);
        $processed = 0;
        
        foreach ($documentPaths as $index => $path) {
            try {
                $result = $this->documentAI->processDocument($path, $documentType);
                $results[] = [
                    'document' => basename($path),
                    'success' => true,
                    'result' => $result
                ];
                $processed++;
                
                // Update progress
                $progress = round(($index + 1) / $total * 100);
                echo "Processed $progress% ($processed/$total)\n";
                
            } catch (Exception $e) {
                $results[] = [
                    'document' => basename($path),
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return [
            'total' => $total,
            'processed' => $processed,
            'failed' => $total - $processed,
            'results' => $results
        ];
    }
    
    /**
     * Train AI models with new data
     */
    public function trainModels(array $trainingData): array {
        $results = [];
        
        // Train valuation model
        if (!empty($trainingData['valuation'])) {
            $results['valuation'] = $this->valuationAI->trainModel($trainingData['valuation']);
        }
        
        // Train fraud detection model
        if (!empty($trainingData['fraud'])) {
            $results['fraud'] = $this->fraudAI->trainModel($trainingData['fraud']);
        }
        
        return $results;
    }
    
    /**
     * Get AI service status
     */
    public function getServiceStatus(): array {
        $status = [
            'valuation_service' => $this->checkValuationService(),
            'document_service' => $this->checkDocumentService(),
            'fraud_service' => $this->checkFraudService(),
            'recommendation_service' => true, // Always available (PHP-based)
            'database_connection' => $this->checkDatabaseConnection(),
            'total_predictions' => $this->getTotalPredictions(),
            'last_training' => $this->getLastTrainingDate()
        ];
        
        $status['overall'] = !in_array(false, $status, true);
        
        return $status;
    }
    
    private function checkValuationService(): bool {
        try {
            $url = AI_VALUATION_SERVICE_URL . '/health';
            $response = @file_get_contents($url);
            if ($response) {
                $data = json_decode($response, true);
                return $data['status'] === 'healthy';
            }
        } catch (Exception $e) {
            error_log("Valuation service check failed: " . $e->getMessage());
        }
        return false;
    }
    
    private function getTotalPredictions(): int {
        $sql = "SELECT COUNT(*) as total FROM ai_predictions";
        $result = mysqli_query($this->conn, $sql);
        $row = mysqli_fetch_assoc($result);
        return $row['total'] ?? 0;
    }
    
    private function getLastTrainingDate(): ?string {
        $sql = "SELECT MAX(trained_at) as last_training FROM ai_model_versions";
        $result = mysqli_query($this->conn, $sql);
        $row = mysqli_fetch_assoc($result);
        return $row['last_training'];
    }
}
?>