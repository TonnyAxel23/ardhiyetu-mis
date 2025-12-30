<?php
namespace ArdhiYetu\AI;

require_once __DIR__ . '/../../vendor/autoload.php';
use thiagoalessio\TesseractOCR\TesseractOCR;

class DocumentProcessorAI {
    private $googleVisionKey;
    private $tesseractPath;
    
    public function __construct() {
        $this->googleVisionKey = GOOGLE_VISION_API_KEY ?? '';
        $this->tesseractPath = TESSERACT_PATH ?? '/usr/bin/tesseract';
    }

    /**
     * Process uploaded document
     */
    public function processDocument(string $filePath, string $documentType): array {
        $results = [
            'extracted_data' => [],
            'verification_results' => [],
            'anomalies' => [],
            'confidence_score' => 0
        ];
        
        // Extract text
        $extractedText = $this->extractText($filePath);
        
        // Analyze based on document type
        switch ($documentType) {
            case 'title_deed':
                $results['extracted_data'] = $this->parseTitleDeed($extractedText);
                $results['verification_results'] = $this->verifyTitleDeed($extractedText);
                break;
                
            case 'id_card':
                $results['extracted_data'] = $this->parseIDCard($extractedText);
                $results['verification_results'] = $this->verifyIDCard($filePath);
                break;
                
            case 'survey_map':
                $results['extracted_data'] = $this->parseSurveyMap($filePath);
                break;
                
            case 'legal_contract':
                $results['extracted_data'] = $this->parseLegalContract($extractedText);
                $results['verification_results'] = $this->checkLegalClauses($extractedText);
                break;
        }
        
        // Detect anomalies
        $results['anomalies'] = $this->detectAnomalies($extractedText, $documentType);
        
        // Calculate confidence
        $results['confidence_score'] = $this->calculateConfidence($results);
        
        // Generate summary
        $results['summary'] = $this->generateSummary($results);
        
        return $results;
    }

    private function extractText(string $filePath): string {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'bmp', 'gif'])) {
            // Use OCR
            try {
                if (class_exists('TesseractOCR')) {
                    return (new TesseractOCR($filePath))
                        ->executable($this->tesseractPath)
                        ->run();
                }
                
                // Fallback to Google Vision API
                if ($this->googleVisionKey) {
                    return $this->googleVisionOCR($filePath);
                }
            } catch (Exception $e) {
                error_log("OCR Error: " . $e->getMessage());
            }
        } elseif ($extension == 'pdf') {
            return $this->extractTextFromPDF($filePath);
        }
        
        return file_get_contents($filePath);
    }

    private function googleVisionOCR(string $filePath): string {
        $imageData = base64_encode(file_get_contents($filePath));
        
        $data = [
            'requests' => [[
                'image' => ['content' => $imageData],
                'features' => [['type' => 'DOCUMENT_TEXT_DETECTION']]
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
        
        return $result['responses'][0]['fullTextAnnotation']['text'] ?? '';
    }

    private function parseTitleDeed(string $text): array {
        $patterns = [
            'parcel_number' => '/PARCEL\s*NO[\.:]?\s*([A-Z0-9\/\-]+)/i',
            'owner_name' => '/OWNER[:\s]+([A-Z\s\.]+)/i',
            'size' => '/AREA[:\s]+([0-9\.]+)\s*(acre|ha|hectare)/i',
            'location' => '/LOCATED\s*AT[:\s]+([A-Za-z0-9\s,\.\-]+)/i',
            'date' => '/DATED[:\s]+([0-9]{1,2}[\/\-][0-9]{1,2}[\/\-][0-9]{4})/i',
            'registry' => '/REGISTRY[:\s]+([A-Za-z0-9\s]+)/i'
        ];
        
        $data = [];
        foreach ($patterns as $key => $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $data[$key] = trim($matches[1]);
            }
        }
        
        return $data;
    }

    private function verifyTitleDeed(string $text): array {
        $verifications = [];
        
        // Check for required sections
        $requiredSections = ['This Deed', 'Witnesseth', 'In Witness Whereof', 'Seal'];
        foreach ($requiredSections as $section) {
            $verifications[$section] = stripos($text, $section) !== false;
        }
        
        // Check for signatures
        $signaturePatterns = [
            'signed_by' => '/SIGNED\s*BY[:\s]+([A-Z\s\.]+)/i',
            'witnessed_by' => '/WITNESSED\s*BY[:\s]+([A-Z\s\.]+)/i'
        ];
        
        foreach ($signaturePatterns as $key => $pattern) {
            $verifications[$key] = preg_match($pattern, $text) === 1;
        }
        
        // Check for official stamps
        $verifications['official_stamp'] = preg_match('/OFFICIAL\s*STAMP|SEAL|EMBOSSMENT/i', $text) === 1;
        
        return $verifications;
    }

    private function detectAnomalies(string $text, string $docType): array {
        $anomalies = [];
        
        // Common anomaly patterns
        $commonAnomalies = [
            'text_overlay' => 'OVERLAY|SUPERIMPOSED|ADDED\s*TEXT',
            'alteration' => 'WHITEOUT|CORRECTION\s*FLUID|ERASURE',
            'inconsistency' => 'DIFFERENT\s*FONTS|VARYING\s*INK',
            'template_mismatch' => 'NON\s*STANDARD\s*FORMAT'
        ];
        
        foreach ($commonAnomalies as $type => $pattern) {
            if (preg_match("/$pattern/i", $text)) {
                $anomalies[] = [
                    'type' => $type,
                    'description' => "Possible $type detected",
                    'severity' => 'medium'
                ];
            }
        }
        
        // Document-specific anomalies
        if ($docType == 'title_deed') {
            if (!preg_match('/MINISTRY\s*OF\s*LANDS|GOVERNMENT\s*OF\s*KENYA/i', $text)) {
                $anomalies[] = [
                    'type' => 'missing_official_header',
                    'description' => 'Missing official government header',
                    'severity' => 'high'
                ];
            }
        }
        
        return $anomalies;
    }

    private function calculateConfidence(array $results): float {
        $score = 0.5; // Base score
        
        // Increase for successful extractions
        $extractedCount = count($results['extracted_data']);
        if ($extractedCount > 3) {
            $score += 0.2;
        }
        
        // Decrease for anomalies
        $anomalyCount = count($results['anomalies']);
        $score -= ($anomalyCount * 0.1);
        
        // Increase for verifications
        $verificationCount = array_sum($results['verification_results']);
        $totalVerifications = count($results['verification_results']);
        if ($totalVerifications > 0) {
            $score += ($verificationCount / $totalVerifications) * 0.3;
        }
        
        return max(0, min(1, $score));
    }

    /**
     * Generate structured data from document
     */
    public function generateStructuredData(array $documentData, string $template = 'standard'): array {
        $templates = [
            'standard' => [
                'metadata' => [
                    'processing_date' => date('Y-m-d H:i:s'),
                    'document_type' => $documentData['type'],
                    'confidence_score' => $documentData['confidence']
                ],
                'extracted_fields' => $documentData['extracted_data'],
                'verifications' => $documentData['verification_results'],
                'anomalies' => $documentData['anomalies']
            ],
            'land_registration' => [
                'parcel_info' => $this->extractParcelInfo($documentData),
                'ownership_info' => $this->extractOwnershipInfo($documentData),
                'boundaries' => $this->extractBoundaryInfo($documentData)
            ]
        ];
        
        return $templates[$template] ?? $templates['standard'];
    }
    
    /**
     * Compare two documents for consistency
     */
    public function compareDocuments(array $doc1, array $doc2): array {
        $comparison = [
            'matching_fields' => [],
            'conflicting_fields' => [],
            'similarity_score' => 0
        ];
        
        // Compare extracted data
        foreach ($doc1['extracted_data'] as $key => $value1) {
            if (isset($doc2['extracted_data'][$key])) {
                $value2 = $doc2['extracted_data'][$key];
                
                $similarity = $this->calculateStringSimilarity($value1, $value2);
                
                if ($similarity > 0.8) {
                    $comparison['matching_fields'][$key] = [
                        'value1' => $value1,
                        'value2' => $value2,
                        'similarity' => $similarity
                    ];
                } else {
                    $comparison['conflicting_fields'][$key] = [
                        'value1' => $value1,
                        'value2' => $value2,
                        'similarity' => $similarity
                    ];
                }
            }
        }
        
        // Calculate overall similarity
        $totalFields = count($comparison['matching_fields']) + count($comparison['conflicting_fields']);
        if ($totalFields > 0) {
            $comparison['similarity_score'] = count($comparison['matching_fields']) / $totalFields;
        }
        
        return $comparison;
    }
    
    private function calculateStringSimilarity(string $str1, string $str2): float {
        $str1 = strtolower(trim($str1));
        $str2 = strtolower(trim($str2));
        
        if ($str1 === $str2) return 1.0;
        
        similar_text($str1, $str2, $percent);
        return $percent / 100;
    }
}
?>