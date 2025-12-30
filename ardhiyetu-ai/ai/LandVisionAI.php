<?php
namespace ArdhiYetu\AI;

class LandVisionAI {
    private $googleVisionKey;
    private $openCvEnabled;
    
    public function __construct() {
        $this->googleVisionKey = GOOGLE_VISION_API_KEY ?? '';
        $this->openCvEnabled = extension_loaded('opencv');
    }
    
    /**
     * Analyze satellite/drone images for land information
     */
    public function analyzeSatelliteImage($imagePath, $coordinates = null): array {
        $analysis = [
            'boundary_detection' => [],
            'encroachment_analysis' => [],
            'vegetation_analysis' => [],
            'construction_detection' => [],
            'soil_analysis' => [],
            'confidence_scores' => []
        ];
        
        try {
            // 1. Boundary detection
            $analysis['boundary_detection'] = $this->detectBoundaries($imagePath);
            
            // 2. Encroachment analysis
            if ($coordinates) {
                $analysis['encroachment_analysis'] = $this->analyzeEncroachment($imagePath, $coordinates);
            }
            
            // 3. Vegetation analysis
            $analysis['vegetation_analysis'] = $this->analyzeVegetation($imagePath);
            
            // 4. Construction detection
            $analysis['construction_detection'] = $this->detectConstruction($imagePath);
            
            // 5. Soil analysis
            $analysis['soil_analysis'] = $this->analyzeSoil($imagePath);
            
            // Calculate overall confidence
            $analysis['confidence_scores'] = $this->calculateConfidence($analysis);
            
            // Generate summary
            $analysis['summary'] = $this->generateSummary($analysis);
            
        } catch (Exception $e) {
            error_log("Land vision analysis failed: " . $e->getMessage());
            $analysis['error'] = $e->getMessage();
        }
        
        return $analysis;
    }
    
    private function detectBoundaries($imagePath): array {
        $results = [
            'detected' => false,
            'boundary_points' => [],
            'perimeter' => 0,
            'area' => 0,
            'confidence' => 0
        ];
        
        if ($this->openCvEnabled) {
            // OpenCV boundary detection
            $image = cv\imread($imagePath);
            $gray = cv\cvtColor($image, cv\COLOR_BGR2GRAY);
            $edges = cv\Canny($gray, 50, 150);
            
            $contours = cv\findContours($edges, cv\RETR_EXTERNAL, cv\CHAIN_APPROX_SIMPLE);
            
            if (count($contours) > 0) {
                $largestContour = $contours[0];
                $results['detected'] = true;
                $results['perimeter'] = cv\arcLength($largestContour, true);
                $results['area'] = cv\contourArea($largestContour);
                $results['confidence'] = 0.8;
            }
        } else if ($this->googleVisionKey) {
            // Use Google Vision API
            $results = $this->googleVisionBoundaryDetection($imagePath);
        }
        
        return $results;
    }
    
    private function googleVisionBoundaryDetection($imagePath): array {
        $imageData = base64_encode(file_get_contents($imagePath));
        
        $data = [
            'requests' => [[
                'image' => ['content' => $imageData],
                'features' => [
                    ['type' => 'LANDMARK_DETECTION'],
                    ['type' => 'IMAGE_PROPERTIES']
                ]
            ]]
        ];
        
        $ch = curl_init('https://vision.googleapis.com/v1/images:annotate?key=' . $this->googleVisionKey);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            return [
                'detected' => !empty($result['responses'][0]['landmarkAnnotations']),
                'confidence' => 0.7
            ];
        }
        
        return ['detected' => false, 'confidence' => 0];
    }
    
    private function analyzeEncroachment($imagePath, $coordinates): array {
        $results = [
            'encroachment_detected' => false,
            'encroachment_area' => 0,
            'encroachment_type' => '',
            'confidence' => 0
        ];
        
        // Compare with cadastral boundaries
        $cadastralData = $this->getCadastralData($coordinates);
        
        if ($cadastralData) {
            // Analyze if current boundaries match cadastral
            $currentBoundaries = $this->detectBoundaries($imagePath);
            
            if ($currentBoundaries['detected']) {
                $deviation = $this->calculateDeviation($currentBoundaries, $cadastralData);
                
                if ($deviation > 0.1) { // More than 10% deviation
                    $results['encroachment_detected'] = true;
                    $results['encroachment_area'] = $deviation * $cadastralData['area'];
                    $results['encroachment_type'] = $this->determineEncroachmentType($currentBoundaries, $cadastralData);
                    $results['confidence'] = min(0.9, $deviation * 5);
                }
            }
        }
        
        return $results;
    }
    
    private function analyzeVegetation($imagePath): array {
        $results = [
            'vegetation_coverage' => 0,
            'vegetation_types' => [],
            'health_score' => 0,
            'ndvi_index' => 0
        ];
        
        if ($this->openCvEnabled) {
            // Calculate NDVI (Normalized Difference Vegetation Index)
            $image = cv\imread($imagePath);
            
            // Convert to HSV for better vegetation detection
            $hsv = cv\cvtColor($image, cv\COLOR_BGR2HSV);
            
            // Define green color range
            $lower_green = new cv\Scalar(40, 40, 40);
            $upper_green = new cv\Scalar(80, 255, 255);
            
            $mask = cv\inRange($hsv, $lower_green, $upper_green);
            $greenPixels = cv\countNonZero($mask);
            $totalPixels = $image->rows * $image->cols;
            
            $results['vegetation_coverage'] = $greenPixels / $totalPixels;
            $results['health_score'] = $this->calculateVegetationHealth($image);
            
            // Estimate vegetation types based on color distribution
            $results['vegetation_types'] = $this->estimateVegetationTypes($image);
        }
        
        return $results;
    }
    
    private function detectConstruction($imagePath): array {
        $results = [
            'construction_present' => false,
            'construction_type' => '',
            'construction_area' => 0,
            'completion_percentage' => 0,
            'confidence' => 0
        ];
        
        if ($this->googleVisionKey) {
            $imageData = base64_encode(file_get_contents($imagePath));
            
            $data = [
                'requests' => [[
                    'image' => ['content' => $imageData],
                    'features' => [
                        ['type' => 'OBJECT_LOCALIZATION', 'maxResults' => 10]
                    ]
                ]]
            ];
            
            $ch = curl_init('https://vision.googleapis.com/v1/images:annotate?key=' . $this->googleVisionKey);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            $result = json_decode($response, true);
            
            if (isset($result['responses'][0]['localizedObjectAnnotations'])) {
                $objects = $result['responses'][0]['localizedObjectAnnotations'];
                
                $constructionObjects = ['building', 'house', 'construction equipment', 'crane', 'excavator'];
                
                foreach ($objects as $object) {
                    if (in_array(strtolower($object['name']), $constructionObjects)) {
                        $results['construction_present'] = true;
                        $results['construction_type'] = $object['name'];
                        $results['construction_area'] = $this->calculateAreaFromBoundingPoly($object['boundingPoly']);
                        $results['confidence'] = $object['score'];
                        break;
                    }
                }
            }
        }
        
        return $results;
    }
    
    private function analyzeSoil($imagePath): array {
        $results = [
            'soil_type' => 'unknown',
            'moisture_level' => 0,
            'erosion_detected' => false,
            'erosion_severity' => 'none',
            'fertility_score' => 0
        ];
        
        if ($this->openCvEnabled) {
            $image = cv\imread($imagePath);
            
            // Analyze color for soil type
            $averageColor = $this->getAverageColor($image);
            $results['soil_type'] = $this->classifySoilType($averageColor);
            
            // Estimate moisture from color darkness
            $results['moisture_level'] = $this->estimateMoisture($averageColor);
            
            // Detect erosion patterns
            $results['erosion_detected'] = $this->detectErosion($image);
            
            // Calculate fertility score
            $results['fertility_score'] = $this->calculateFertilityScore($image);
        }
        
        return $results;
    }
    
    private function calculateConfidence(array $analysis): array {
        $confidences = [];
        
        foreach ($analysis as $key => $value) {
            if (is_array($value) && isset($value['confidence'])) {
                $confidences[$key] = $value['confidence'];
            }
        }
        
        return [
            'individual' => $confidences,
            'overall' => array_sum($confidences) / max(1, count($confidences)),
            'reliable' => (array_sum($confidences) / max(1, count($confidences))) > 0.6
        ];
    }
    
    private function generateSummary(array $analysis): string {
        $summary = [];
        
        if ($analysis['boundary_detection']['detected']) {
            $summary[] = sprintf("Clear boundaries detected with %.1f%% confidence", 
                $analysis['boundary_detection']['confidence'] * 100);
        }
        
        if ($analysis['encroachment_analysis']['encroachment_detected']) {
            $summary[] = sprintf("Encroachment detected: %s (%.2f sq m)", 
                $analysis['encroachment_analysis']['encroachment_type'],
                $analysis['encroachment_analysis']['encroachment_area']);
        }
        
        if ($analysis['vegetation_analysis']['vegetation_coverage'] > 0) {
            $summary[] = sprintf("Vegetation coverage: %.1f%%", 
                $analysis['vegetation_analysis']['vegetation_coverage'] * 100);
        }
        
        if ($analysis['construction_detection']['construction_present']) {
            $summary[] = sprintf("Construction detected: %s", 
                $analysis['construction_detection']['construction_type']);
        }
        
        return implode('. ', $summary);
    }
    
    /**
     * Compare historical images for change detection
     */
    public function detectChanges($currentImage, $historicalImage, $timeInterval = '1 year'): array {
        $changes = [
            'change_detected' => false,
            'change_type' => '',
            'change_area' => 0,
            'change_percentage' => 0,
            'change_timeline' => []
        ];
        
        try {
            if ($this->openCvEnabled) {
                $current = cv\imread($currentImage);
                $historical = cv\imread($historicalImage);
                
                // Ensure same dimensions
                if ($current->rows == $historical->rows && $current->cols == $historical->cols) {
                    $diff = cv\absdiff($current, $historical);
                    $gray = cv\cvtColor($diff, cv\COLOR_BGR2GRAY);
                    $threshold = cv\threshold($gray, 30, 255, cv\THRESH_BINARY);
                    
                    $changedPixels = cv\countNonZero($threshold);
                    $totalPixels = $current->rows * $current->cols;
                    
                    $changePercentage = $changedPixels / $totalPixels;
                    
                    if ($changePercentage > 0.05) { // 5% change threshold
                        $changes['change_detected'] = true;
                        $changes['change_percentage'] = $changePercentage;
                        $changes['change_type'] = $this->classifyChangeType($current, $historical);
                        $changes['change_area'] = $this->estimateChangeArea($changePercentage);
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Change detection failed: " . $e->getMessage());
        }
        
        return $changes;
    }
    
    /**
     * Generate land health report
     */
    public function generateLandHealthReport($imagePath, $coordinates): array {
        $analysis = $this->analyzeSatelliteImage($imagePath, $coordinates);
        
        $report = [
            'overall_health_score' => $this->calculateHealthScore($analysis),
            'land_use_compliance' => $this->checkLandUseCompliance($coordinates),
            'environmental_impact' => $this->assessEnvironmentalImpact($analysis),
            'recommendations' => $this->generateRecommendations($analysis),
            'monitoring_schedule' => $this->suggestMonitoringSchedule($analysis),
            'risk_assessment' => $this->assessRisks($analysis)
        ];
        
        return $report;
    }
    
    private function calculateHealthScore(array $analysis): float {
        $scores = [];
        
        // Vegetation contributes positively
        if (isset($analysis['vegetation_analysis']['health_score'])) {
            $scores[] = $analysis['vegetation_analysis']['health_score'] * 0.3;
        }
        
        // Construction/encroachment contribute negatively
        if ($analysis['construction_detection']['construction_present']) {
            $scores[] = -0.2;
        }
        
        if ($analysis['encroachment_analysis']['encroachment_detected']) {
            $scores[] = -0.3;
        }
        
        // Soil fertility contributes positively
        if (isset($analysis['soil_analysis']['fertility_score'])) {
            $scores[] = $analysis['soil_analysis']['fertility_score'] * 0.2;
        }
        
        $baseScore = 0.5; // Neutral baseline
        $totalScore = $baseScore + array_sum($scores);
        
        return max(0, min(1, $totalScore));
    }
    
    private function checkLandUseCompliance($coordinates): array {
        // This would integrate with zoning regulations database
        return [
            'compliant' => true, // Placeholder
            'zoning_type' => 'agricultural',
            'restrictions' => [],
            'violations' => []
        ];
    }
    
    private function assessEnvironmentalImpact(array $analysis): array {
        $impact = [
            'score' => 0,
            'concerns' => [],
            'positive_aspects' => []
        ];
        
        if ($analysis['construction_detection']['construction_present']) {
            $impact['concerns'][] = 'Construction activity detected';
            $impact['score'] -= 0.3;
        }
        
        if ($analysis['vegetation_analysis']['vegetation_coverage'] > 0.7) {
            $impact['positive_aspects'][] = 'High vegetation coverage';
            $impact['score'] += 0.2;
        }
        
        if ($analysis['soil_analysis']['erosion_detected']) {
            $impact['concerns'][] = 'Soil erosion detected';
            $impact['score'] -= 0.2;
        }
        
        $impact['score'] = max(0, min(1, 0.5 + $impact['score']));
        
        return $impact;
    }
    
    private function generateRecommendations(array $analysis): array {
        $recommendations = [];
        
        if ($analysis['encroachment_analysis']['encroachment_detected']) {
            $recommendations[] = [
                'priority' => 'high',
                'action' => 'Survey land boundaries to resolve encroachment',
                'timeline' => 'immediate'
            ];
        }
        
        if ($analysis['vegetation_analysis']['vegetation_coverage'] < 0.3) {
            $recommendations[] = [
                'priority' => 'medium',
                'action' => 'Consider reforestation or ground cover planting',
                'timeline' => '3 months'
            ];
        }
        
        if ($analysis['soil_analysis']['erosion_detected']) {
            $recommendations[] = [
                'priority' => 'medium',
                'action' => 'Implement erosion control measures',
                'timeline' => '1 month'
            ];
        }
        
        return $recommendations;
    }
}
?>