<?php
namespace ArdhiYetu\AI;

require_once __DIR__ . '/../../includes/init.php';

class LandValuationAI {
    private $conn;
    private $modelPath = __DIR__ . '/../../models/valuation_model.pkl';
    private $apiEndpoint = 'http://localhost:5000/api/ai/valuate';

    public function __construct($conn = null) {
        $this->conn = $conn ?? $GLOBALS['conn'];
    }

    /**
     * Estimate land value using ML model
     */
    public function estimateValue(array $data): array {
        $features = $this->extractFeatures($data);
        
        // Try Python microservice first
        $pythonPrediction = $this->callPythonService($features);
        
        if ($pythonPrediction['success']) {
            return [
                'estimated_value' => $pythonPrediction['value'],
                'confidence' => $pythonPrediction['confidence'],
                'factors' => $this->analyzeFactors($features),
                'method' => 'ml_model'
            ];
        }
        
        // Fallback to rule-based estimation
        return $this->ruleBasedEstimation($features);
    }

    private function extractFeatures(array $data): array {
        return [
            'location_score' => $this->calculateLocationScore($data['location']),
            'size' => floatval($data['size']),
            'land_type' => $data['land_type'] ?? 'agricultural',
            'infrastructure' => $this->getInfrastructureScore($data),
            'market_trend' => $this->getMarketTrend($data['county']),
            'proximity_amenities' => $this->getAmenityScore($data['coordinates']),
            'zoning' => $data['zoning'] ?? 'residential',
            'soil_quality' => $data['soil_quality'] ?? 0.5,
            'accessibility' => $data['accessibility'] ?? 0.5
        ];
    }

    private function callPythonService(array $features): array {
        try {
            $ch = curl_init($this->apiEndpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($features));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . AI_API_KEY
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                return json_decode($response, true);
            }
        } catch (Exception $e) {
            error_log("AI Service Error: " . $e->getMessage());
        }
        
        return ['success' => false];
    }

    private function ruleBasedEstimation(array $features): array {
        // Base price per acre by county
        $countyPrices = [
            'nairobi' => 5000000,
            'kiambu' => 3000000,
            'mombasa' => 4000000,
            'kisumu' => 2000000,
            'nakuru' => 1500000
        ];
        
        $county = strtolower($features['county'] ?? 'other');
        $basePrice = $countyPrices[$county] ?? 1000000;
        
        // Adjustments
        $adjustments = [
            'size' => $features['size'] * 0.8,
            'location' => $features['location_score'] * 1.2,
            'infrastructure' => $features['infrastructure'] * 0.5,
            'zoning' => $this->getZoningMultiplier($features['zoning']),
            'amenities' => $features['proximity_amenities'] * 0.3
        ];
        
        $estimatedValue = $basePrice * array_sum($adjustments);
        
        return [
            'estimated_value' => round($estimatedValue, 2),
            'confidence' => 0.7,
            'factors' => $adjustments,
            'method' => 'rule_based'
        ];
    }

    private function calculateLocationScore(string $location): float {
        // Analyze location keywords
        $keywords = [
            'cbd' => 0.9,
            'suburb' => 0.7,
            'rural' => 0.4,
            'industrial' => 0.6,
            'residential' => 0.8
        ];
        
        $score = 0.5; // Default
        $location = strtolower($location);
        
        foreach ($keywords as $keyword => $value) {
            if (strpos($location, $keyword) !== false) {
                $score = $value;
                break;
            }
        }
        
        return $score;
    }

    private function getMarketTrend(string $county): float {
        // Query historical data
        $sql = "SELECT AVG(price_per_acre) as avg_price,
                       COUNT(*) as transaction_count
                FROM historical_transactions 
                WHERE county = ? AND transaction_date > DATE_SUB(NOW(), INTERVAL 6 MONTH)";
        
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 's', $county);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $data = mysqli_fetch_assoc($stmt);
        
        if ($data && $data['transaction_count'] > 10) {
            return $data['avg_price'] > 0 ? 1.0 : 0.5;
        }
        
        return 0.5;
    }

    private function analyzeFactors(array $features): array {
        return [
            'location_impact' => round($features['location_score'] * 100) . '%',
            'size_impact' => round($features['size'] / 10, 2) . 'x',
            'infrastructure_impact' => round($features['infrastructure'] * 100) . '%',
            'market_condition' => $features['market_trend'] > 0.7 ? 'Favorable' : 'Moderate'
        ];
    }

    /**
     * Get valuation history for a location
     */
    public function getValuationHistory(string $location, int $limit = 10): array {
        $sql = "SELECT * FROM ai_predictions 
                WHERE location_hash = MD5(?) 
                ORDER BY created_at DESC 
                LIMIT ?";
        
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'si', $location, $limit);
        mysqli_stmt_execute($stmt);
        
        return mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    }

    /**
     * Train model with new data
     */
    public function trainModel(array $trainingData): bool {
        // Collect training data
        $this->saveTrainingData($trainingData);
        
        // Trigger Python service training
        $this->triggerModelRetraining();
        
        return true;
    }

    private function saveTrainingData(array $data): void {
        $sql = "INSERT INTO ml_training_data 
                (features, target_value, source, verified) 
                VALUES (?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($this->conn, $sql);
        
        foreach ($data as $item) {
            $features = json_encode($item['features']);
            $target = $item['actual_value'];
            $source = $item['source'] ?? 'manual';
            $verified = $item['verified'] ? 1 : 0;
            
            mysqli_stmt_bind_param($stmt, 'sdsi', 
                $features, $target, $source, $verified);
            mysqli_stmt_execute($stmt);
        }
    }
}
?>