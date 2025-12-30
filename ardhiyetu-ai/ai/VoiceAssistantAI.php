<?php
namespace ArdhiYetu\AI;

class VoiceAssistantAI {
    private $googleSpeechKey;
    private $whisperEnabled;
    private $language;
    
    public function __construct($language = 'sw') {
        $this->googleSpeechKey = GOOGLE_SPEECH_API_KEY ?? '';
        $this->whisperEnabled = function_exists('whisper_transcribe');
        $this->language = $this->validateLanguage($language);
    }
    
    private function validateLanguage($lang): string {
        $supported = ['sw', 'en', 'fr', 'ar', 'so'];
        return in_array($lang, $supported) ? $lang : 'sw';
    }
    
    /**
     * Process voice command for land management
     */
    public function processVoiceCommand($audioFile, $context = 'general'): array {
        $response = [
            'transcription' => '',
            'intent' => '',
            'entities' => [],
            'action' => '',
            'confidence' => 0,
            'response' => ''
        ];
        
        try {
            // 1. Transcribe speech to text
            $transcription = $this->transcribeSpeech($audioFile);
            $response['transcription'] = $transcription;
            
            if (empty($transcription)) {
                return $response;
            }
            
            // 2. Detect intent
            $intentAnalysis = $this->detectIntent($transcription, $context);
            $response['intent'] = $intentAnalysis['intent'];
            $response['confidence'] = $intentAnalysis['confidence'];
            $response['entities'] = $intentAnalysis['entities'];
            
            // 3. Determine action
            $action = $this->determineAction($intentAnalysis, $context);
            $response['action'] = $action;
            
            // 4. Generate response
            $response['response'] = $this->generateResponse($transcription, $action, $intentAnalysis);
            
        } catch (Exception $e) {
            error_log("Voice processing failed: " . $e->getMessage());
            $response['response'] = $this->getFallbackResponse($e->getMessage());
        }
        
        return $response;
    }
    
    private function transcribeSpeech($audioFile): string {
        // Check file type and size
        $maxSize = 10 * 1024 * 1024; // 10MB
        $fileSize = filesize($audioFile);
        
        if ($fileSize > $maxSize) {
            throw new Exception("Audio file too large. Maximum size is 10MB.");
        }
        
        // Try Google Speech-to-Text first
        if ($this->googleSpeechKey) {
            $text = $this->googleSpeechTranscription($audioFile);
            if (!empty($text)) {
                return $text;
            }
        }
        
        // Try Whisper if available
        if ($this->whisperEnabled) {
            $text = whisper_transcribe($audioFile);
            if (!empty($text)) {
                return $text;
            }
        }
        
        // Fallback to basic transcription
        return $this->basicTranscription($audioFile);
    }
    
    private function googleSpeechTranscription($audioFile): string {
        $audioContent = file_get_contents($audioFile);
        $encodedAudio = base64_encode($audioContent);
        
        $data = [
            'config' => [
                'encoding' => 'LINEAR16',
                'sampleRateHertz' => 16000,
                'languageCode' => $this->language . '-KE',
                'enableAutomaticPunctuation' => true,
                'model' => 'command_and_search'
            ],
            'audio' => [
                'content' => $encodedAudio
            ]
        ];
        
        $ch = curl_init('https://speech.googleapis.com/v1/speech:recognize?key=' . $this->googleSpeechKey);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            if (isset($result['results'][0]['alternatives'][0]['transcript'])) {
                return $result['results'][0]['alternatives'][0]['transcript'];
            }
        }
        
        return '';
    }
    
    private function detectIntent($text, $context): array {
        $intents = $this->getIntentsByContext($context);
        
        $bestMatch = [
            'intent' => 'unknown',
            'confidence' => 0,
            'entities' => []
        ];
        
        $text = strtolower($text);
        
        foreach ($intents as $intent => $patterns) {
            foreach ($patterns['patterns'] as $pattern) {
                similar_text($text, $pattern, $percent);
                $percent = $percent / 100;
                
                // Adjust for partial matches
                if (strpos($text, $pattern) !== false) {
                    $percent = max($percent, 0.8);
                }
                
                if ($percent > $bestMatch['confidence']) {
                    $bestMatch['intent'] = $intent;
                    $bestMatch['confidence'] = $percent;
                    $bestMatch['entities'] = $this->extractEntities($text, $patterns['entities'] ?? []);
                }
            }
        }
        
        return $bestMatch;
    }
    
    private function getIntentsByContext($context): array {
        $intents = [
            'land_registration' => [
                'register_land' => [
                    'patterns' => [
                        'nataka kusajili ardhi',
                        'how do i register land',
                        'registration ya ardhi',
                        'kujiandikisha ardhi'
                    ],
                    'entities' => ['location', 'size', 'parcel_number']
                ],
                'check_status' => [
                    'patterns' => [
                        'angalia hali ya usajili',
                        'check registration status',
                        'status ya usajili'
                    ],
                    'entities' => ['parcel_number', 'reference_number']
                ]
            ],
            'land_transfer' => [
                'initiate_transfer' => [
                    'patterns' => [
                        'nataka kuhamisha ardhi',
                        'how to transfer land',
                        'transfer ownership',
                        'hamisha umiliki'
                    ],
                    'entities' => ['parcel_number', 'recipient', 'price']
                ],
                'track_transfer' => [
                    'patterns' => [
                        'fuatilia uhamisho',
                        'track transfer status',
                        'hali ya uhamisho'
                    ],
                    'entities' => ['transfer_id', 'parcel_number']
                ]
            ],
            'general' => [
                'greeting' => [
                    'patterns' => ['hujambo', 'hello', 'habari', 'good morning'],
                    'entities' => []
                ],
                'help' => [
                    'patterns' => ['saidia', 'help', 'msaada', 'need assistance'],
                    'entities' => []
                ],
                'weather' => [
                    'patterns' => ['hali ya hewa', 'weather', 'mvua', 'rain'],
                    'entities' => ['location']
                ]
            ]
        ];
        
        return $intents[$context] ?? $intents['general'];
    }
    
    private function extractEntities($text, $entityTypes): array {
        $entities = [];
        
        foreach ($entityTypes as $type) {
            $values = $this->extractEntity($text, $type);
            if (!empty($values)) {
                $entities[$type] = $values;
            }
        }
        
        return $entities;
    }
    
    private function extractEntity($text, $type): array {
        $patterns = [
            'location' => ['/\b(nairobi|mombasa|kisumu|nakuru|eldoret)\b/i'],
            'size' => ['/(\d+(?:\.\d+)?)\s*(acre|hectare|ha|acres)/i'],
            'price' => ['/(?:sh|kes|k?shs?)\s*(\d+(?:,\d+)*(?:\.\d+)?)/i', '/\b(\d+(?:,\d+)*(?:\.\d+)?)\s*(?:shillings|bob)/i'],
            'parcel_number' => ['/\b(LR\s*\d+\/?\d*)\b/i', '/\b(parcel\s*#?\s*[\w\d\/\-]+)\b/i'],
            'date' => ['/(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4})/', '/(tomorrow|today|next week|next month)/i']
        ];
        
        if (!isset($patterns[$type])) {
            return [];
        }
        
        $matches = [];
        foreach ($patterns[$type] as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return array_slice($matches, 1);
            }
        }
        
        return [];
    }
    
    private function determineAction(array $intentAnalysis, $context): string {
        $intent = $intentAnalysis['intent'];
        
        $actionMap = [
            'register_land' => 'redirect:/user/my-lands.php?action=add',
            'check_status' => 'query:land_status',
            'initiate_transfer' => 'redirect:/user/transfer-land.php',
            'track_transfer' => 'query:transfer_status',
            'greeting' => 'response:greeting',
            'help' => 'response:help',
            'weather' => 'api:weather'
        ];
        
        return $actionMap[$intent] ?? 'response:fallback';
    }
    
    private function generateResponse($transcription, $action, $intentAnalysis): string {
        list($type, $value) = explode(':', $action . ':');
        
        switch ($type) {
            case 'redirect':
                return $this->generateRedirectResponse($value, $intentAnalysis);
            case 'query':
                return $this->generateQueryResponse($value, $intentAnalysis);
            case 'api':
                return $this->generateApiResponse($value, $intentAnalysis);
            case 'response':
                return $this->generatePredefinedResponse($value, $intentAnalysis);
            default:
                return $this->getFallbackResponse();
        }
    }
    
    private function generateRedirectResponse($url, $intentAnalysis): string {
        $responses = [
            'sw' => [
                'register_land' => "Nimekuelewa unataka kusajili ardhi. Nitaikupeleka kwenye ukurasa wa usajili.",
                'initiate_transfer' => "Nimekuelewa unataka kuhamisha ardhi. Nitaikupeleka kwenye ukurasa wa uhamisho.",
                'default' => "Nitaikupeleka kwenye ukurasa unaohitajika."
            ],
            'en' => [
                'register_land' => "I understand you want to register land. I'll take you to the registration page.",
                'initiate_transfer' => "I understand you want to transfer land. I'll take you to the transfer page.",
                'default' => "I'll take you to the appropriate page."
            ]
        ];
        
        $lang = $this->language;
        $intent = $intentAnalysis['intent'];
        
        $response = $responses[$lang][$intent] ?? $responses[$lang]['default'] ?? $responses['en']['default'];
        
        // Add URL for web interface
        $response .= " [ACTION:redirect:$url]";
        
        return $response;
    }
    
    /**
     * Convert text response to speech
     */
    public function textToSpeech($text, $language = null, $gender = 'female'): string {
        $language = $language ?? $this->language;
        
        // Use Google Text-to-Speech if available
        if ($this->googleSpeechKey) {
            return $this->googleTextToSpeech($text, $language, $gender);
        }
        
        // Generate audio file path
        $audioDir = __DIR__ . '/../../uploads/voice/';
        if (!is_dir($audioDir)) {
            mkdir($audioDir, 0755, true);
        }
        
        $filename = 'response_' . md5($text . $language . $gender) . '.mp3';
        $filepath = $audioDir . $filename;
        
        // For demo, create placeholder
        if (!file_exists($filepath)) {
            file_put_contents($filepath, '');
        }
        
        return $filepath;
    }
    
    private function googleTextToSpeech($text, $language, $gender): string {
        $voices = [
            'sw' => ['female' => 'sw-KE-TUKpefH9vNeural', 'male' => 'sw-KE-KEZAMwTpNeural'],
            'en' => ['female' => 'en-US-Neural2-F', 'male' => 'en-US-Neural2-D']
        ];
        
        $voice = $voices[$language][$gender] ?? $voices['en']['female'];
        
        $data = [
            'input' => ['text' => $text],
            'voice' => [
                'languageCode' => str_replace('-KE', '', $language) . '-KE',
                'name' => $voice
            ],
            'audioConfig' => [
                'audioEncoding' => 'MP3',
                'speakingRate' => 1.0,
                'pitch' => 0
            ]
        ];
        
        $ch = curl_init('https://texttospeech.googleapis.com/v1/text:synthesize?key=' . $this->googleSpeechKey);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            if (isset($result['audioContent'])) {
                $audioContent = base64_decode($result['audioContent']);
                
                $audioDir = __DIR__ . '/../../uploads/voice/';
                if (!is_dir($audioDir)) {
                    mkdir($audioDir, 0755, true);
                }
                
                $filename = 'response_' . md5($text) . '.mp3';
                $filepath = $audioDir . $filename;
                
                file_put_contents($filepath, $audioContent);
                
                return $filepath;
            }
        }
        
        return '';
    }
    
    /**
     * Handle voice-based authentication
     */
    public function voiceAuthentication($audioFile, $userId): array {
        $result = [
            'authenticated' => false,
            'confidence' => 0,
            'voiceprint_match' => false,
            'text_match' => false
        ];
        
        try {
            // Transcribe voice
            $transcription = $this->transcribeSpeech($audioFile);
            
            // Get user's authentication phrase
            $authPhrase = $this->getUserAuthPhrase($userId);
            
            // Compare with transcription
            similar_text(strtolower($transcription), strtolower($authPhrase), $percent);
            $result['text_match'] = $percent > 80;
            $result['confidence'] = $percent / 100;
            
            // Voiceprint analysis (simplified)
            $result['voiceprint_match'] = $this->analyzeVoiceprint($audioFile, $userId);
            
            // Overall authentication
            $result['authenticated'] = $result['text_match'] && $result['voiceprint_match'];
            
        } catch (Exception $e) {
            error_log("Voice authentication failed: " . $e->getMessage());
        }
        
        return $result;
    }
    
    private function getUserAuthPhrase($userId): string {
        // In production, fetch from database
        $phrases = [
            'Mji wa Nairobi',
            'Ardhi Yetu digital',
            'Namba yangu siri'
        ];
        
        return $phrases[$userId % count($phrases)] ?? 'Ardhi Yetu';
    }
    
    /**
     * Generate voice report for land analysis
     */
    public function generateVoiceReport(array $landData, $language = 'sw'): string {
        $report = "";
        
        if ($language === 'sw') {
            $report = "Ripoti ya Ardhi:\n";
            $report .= "Namba ya kifungu: " . ($landData['parcel_no'] ?? 'Hakuna') . "\n";
            $report .= "Mahali: " . ($landData['location'] ?? 'Hakuna') . "\n";
            $report .= "Ukubwa: " . ($landData['size'] ?? 0) . " ekari\n";
            
            if (isset($landData['valuation'])) {
                $report .= "Thamani ya soko: KSh " . number_format($landData['valuation']['estimated_value'] ?? 0) . "\n";
            }
            
            if (isset($landData['status'])) {
                $report .= "Hali: " . $landData['status'] . "\n";
            }
        } else {
            $report = "Land Report:\n";
            $report .= "Parcel Number: " . ($landData['parcel_no'] ?? 'None') . "\n";
            $report .= "Location: " . ($landData['location'] ?? 'None') . "\n";
            $report .= "Size: " . ($landData['size'] ?? 0) . " acres\n";
            
            if (isset($landData['valuation'])) {
                $report .= "Market Value: KSh " . number_format($landData['valuation']['estimated_value'] ?? 0) . "\n";
            }
            
            if (isset($landData['status'])) {
                $report .= "Status: " . $landData['status'] . "\n";
            }
        }
        
        return $report;
    }
}
?>