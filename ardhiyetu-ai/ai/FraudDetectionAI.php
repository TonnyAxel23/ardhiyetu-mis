<?php
namespace ArdhiYetu\AI;

class FraudDetectionAI {
    private $conn;
    private $riskThreshold = 0.7;
    
    public function __construct($conn = null) {
        $this->conn = $conn ?? $GLOBALS['conn'];
    }
    
    /**
     * Analyze transaction for potential fraud
     */
    public function analyzeTransaction(array $transaction): array {
        $riskFactors = [];
        $totalRisk = 0;
        $factorCount = 0;
        
        // 1. Price Analysis
        $priceRisk = $this->analyzePrice($transaction);
        if ($priceRisk['risk_score'] > 0) {
            $riskFactors['price_anomaly'] = $priceRisk;
            $totalRisk += $priceRisk['risk_score'];
            $factorCount++;
        }
        
        // 2. Frequency Analysis
        $frequencyRisk = $this->analyzeFrequency($transaction);
        if ($frequencyRisk['risk_score'] > 0) {
            $riskFactors['frequency_anomaly'] = $frequencyRisk;
            $totalRisk += $frequencyRisk['risk_score'];
            $factorCount++;
        }
        
        // 3. Party Analysis
        $partyRisk = $this->analyzeParties($transaction);
        if ($partyRisk['risk_score'] > 0) {
            $riskFactors['party_risk'] = $partyRisk;
            $totalRisk += $partyRisk['risk_score'];
            $factorCount++;
        }
        
        // 4. Pattern Analysis
        $patternRisk = $this->analyzePatterns($transaction);
        if ($patternRisk['risk_score'] > 0) {
            $riskFactors['pattern_anomaly'] = $patternRisk;
            $totalRisk += $patternRisk['risk_score'];
            $factorCount++;
        }
        
        // Calculate overall risk
        $overallRisk = $factorCount > 0 ? ($totalRisk / $factorCount) : 0;
        
        $result = [
            'risk_score' => round($overallRisk, 3),
            'risk_level' => $this->getRiskLevel($overallRisk),
            'factors' => $riskFactors,
            'recommendation' => $this->getRecommendation($overallRisk),
            'requires_review' => $overallRisk > $this->riskThreshold
        ];
        
        // Log the analysis
        $this->logAnalysis($transaction, $result);
        
        return $result;
    }
    
    private function analyzePrice(array $transaction): array {
        $risk = 0;
        $reasons = [];
        
        // Get market price for area
        $marketPrice = $this->getMarketPrice(
            $transaction['location'],
            $transaction['size']
        );
        
        if ($marketPrice > 0) {
            $priceRatio = $transaction['price'] / $marketPrice;
            
            // Check for undervaluation (potential tax evasion)
            if ($priceRatio < 0.5) {
                $risk = 0.8;
                $reasons[] = "Price significantly below market value (".round($priceRatio*100)."% of market)";
            }
            
            // Check for overvaluation (potential money laundering)
            if ($priceRatio > 2.0) {
                $risk = 0.9;
                $reasons[] = "Price significantly above market value (".round($priceRatio*100)."% of market)";
            }
        }
        
        // Check for round numbers (common in fraudulent transactions)
        if (fmod($transaction['price'], 1000000) == 0) {
            $risk = max($risk, 0.3);
            $reasons[] = "Suspicious round number price";
        }
        
        return [
            'risk_score' => $risk,
            'reasons' => $reasons,
            'market_price' => $marketPrice
        ];
    }
    
    private function analyzeFrequency(array $transaction): array {
        $risk = 0;
        $reasons = [];
        
        // Check sender's transaction frequency
        $senderFrequency = $this->getTransactionFrequency(
            $transaction['from_user_id'],
            '30 days'
        );
        
        if ($senderFrequency > 5) {
            $risk = 0.6;
            $reasons[] = "Sender has high transaction frequency ($senderFrequency in 30 days)";
        }
        
        // Check recipient's transaction frequency
        $recipientFrequency = $this->getTransactionFrequency(
            $transaction['to_user_id'],
            '30 days'
        );
        
        if ($recipientFrequency > 10) {
            $risk = max($risk, 0.7);
            $reasons[] = "Recipient has very high transaction frequency ($recipientFrequency in 30 days)";
        }
        
        // Check for circular transactions
        if ($this->checkCircularTransactions($transaction)) {
            $risk = 0.9;
            $reasons[] = "Possible circular transaction pattern detected";
        }
        
        return [
            'risk_score' => $risk,
            'reasons' => $reasons,
            'sender_frequency' => $senderFrequency,
            'recipient_frequency' => $recipientFrequency
        ];
    }
    
    private function analyzeParties(array $transaction): array {
        $risk = 0;
        $reasons = [];
        
        // Check if parties are related
        if ($this->arePartiesRelated($transaction['from_user_id'], $transaction['to_user_id'])) {
            $risk = 0.4;
            $reasons[] = "Parties appear to be related";
        }
        
        // Check if sender is on watchlist
        if ($this->isOnWatchlist($transaction['from_user_id'])) {
            $risk = 0.8;
            $reasons[] = "Sender is on watchlist";
        }
        
        // Check if recipient is on watchlist
        if ($this->isOnWatchlist($transaction['to_user_id'])) {
            $risk = max($risk, 0.8);
            $reasons[] = "Recipient is on watchlist";
        }
        
        // Check for new accounts
        if ($this->isNewAccount($transaction['from_user_id'])) {
            $risk = max($risk, 0.3);
            $reasons[] = "Sender account is new";
        }
        
        if ($this->isNewAccount($transaction['to_user_id'])) {
            $risk = max($risk, 0.3);
            $reasons[] = "Recipient account is new";
        }
        
        return [
            'risk_score' => $risk,
            'reasons' => $reasons
        ];
    }
    
    private function analyzePatterns(array $transaction): array {
        $risk = 0;
        $reasons = [];
        
        // Check for structuring (breaking large transactions into smaller ones)
        $structuringRisk = $this->detectStructuring($transaction);
        if ($structuringRisk > 0) {
            $risk = $structuringRisk;
            $reasons[] = "Possible transaction structuring detected";
        }
        
        // Check for rapid succession transactions
        if ($this->detectRapidSuccession($transaction)) {
            $risk = max($risk, 0.7);
            $reasons[] = "Rapid succession transactions detected";
        }
        
        // Check for unusual time (late night/early morning)
        $hour = date('H', strtotime($transaction['timestamp']));
        if ($hour < 6 || $hour > 22) {
            $risk = max($risk, 0.4);
            $reasons[] = "Transaction at unusual time ($hour:00)";
        }
        
        return [
            'risk_score' => $risk,
            'reasons' => $reasons
        ];
    }
    
    private function getMarketPrice(string $location, float $size): float {
        $sql = "SELECT AVG(price_per_acre) as avg_price
                FROM historical_transactions 
                WHERE location LIKE ? 
                AND transaction_date > DATE_SUB(NOW(), INTERVAL 6 MONTH)
                AND size BETWEEN ? * 0.5 AND ? * 1.5";
        
        $stmt = mysqli_prepare($this->conn, $sql);
        $locationPattern = "%$location%";
        $sizeLower = $size * 0.5;
        $sizeUpper = $size * 1.5;
        
        mysqli_stmt_bind_param($stmt, 'sdd', $locationPattern, $sizeLower, $sizeUpper);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        
        return $row['avg_price'] ?? 0;
    }
    
    private function getTransactionFrequency(int $userId, string $period = '30 days'): int {
        $sql = "SELECT COUNT(*) as count
                FROM ownership_transfers
                WHERE (from_user_id = ? OR to_user_id = ?)
                AND submitted_at > DATE_SUB(NOW(), INTERVAL ? DAY)";
        
        $days = (int) str_replace(' days', '', $period);
        
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'iii', $userId, $userId, $days);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        
        return $row['count'] ?? 0;
    }
    
    private function checkCircularTransactions(array $transaction): bool {
        // Check if A -> B -> A pattern exists
        $sql = "SELECT COUNT(*) as count
                FROM ownership_transfers t1
                JOIN ownership_transfers t2 ON t1.to_user_id = t2.from_user_id
                WHERE t1.from_user_id = ?
                AND t2.to_user_id = ?
                AND t1.submitted_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
                AND t2.submitted_at > DATE_SUB(NOW(), INTERVAL 7 DAY)";
        
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ii', 
            $transaction['to_user_id'], 
            $transaction['from_user_id']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        
        return ($row['count'] ?? 0) > 0;
    }
    
    private function arePartiesRelated(int $userId1, int $userId2): bool {
        // Check same address, phone, email patterns
        $sql = "SELECT COUNT(*) as matches
                FROM (
                    SELECT email, phone, address FROM users WHERE user_id = ?
                    UNION ALL
                    SELECT email, phone, address FROM users WHERE user_id = ?
                ) as user_data
                GROUP BY email, phone, address
                HAVING COUNT(*) > 1";
        
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ii', $userId1, $userId2);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        
        return ($row['matches'] ?? 0) > 0;
    }
    
    private function isOnWatchlist(int $userId): bool {
        $sql = "SELECT 1 FROM fraud_watchlist WHERE user_id = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        return mysqli_num_rows($result) > 0;
    }
    
    private function isNewAccount(int $userId): bool {
        $sql = "SELECT created_at FROM users WHERE user_id = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        
        if ($row) {
            $accountAge = time() - strtotime($row['created_at']);
            return $accountAge < (30 * 24 * 60 * 60); // Less than 30 days
        }
        
        return false;
    }
    
    private function detectStructuring(array $transaction): float {
        // Check for multiple transactions just below reporting threshold
        $threshold = 10000000; // 10M Ksh
        
        if ($transaction['price'] < $threshold) {
            // Check for similar transactions around same time
            $sql = "SELECT SUM(price) as total, COUNT(*) as count
                    FROM ownership_transfers
                    WHERE from_user_id = ?
                    AND submitted_at > DATE_SUB(?, INTERVAL 24 HOUR)
                    AND price < ?";
            
            $stmt = mysqli_prepare($this->conn, $sql);
            $timestamp = $transaction['timestamp'];
            mysqli_stmt_bind_param($stmt, 'isi', 
                $transaction['from_user_id'], 
                $timestamp,
                $threshold);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);
            
            if ($row && $row['count'] > 2 && $row['total'] > $threshold) {
                return 0.8; // High risk of structuring
            }
        }
        
        return 0;
    }
    
    private function detectRapidSuccession(array $transaction): bool {
        $sql = "SELECT COUNT(*) as count
                FROM ownership_transfers
                WHERE from_user_id = ?
                AND submitted_at > DATE_SUB(?, INTERVAL 1 HOUR)";
        
        $stmt = mysqli_prepare($this->conn, $sql);
        $timestamp = $transaction['timestamp'];
        mysqli_stmt_bind_param($stmt, 'is', $transaction['from_user_id'], $timestamp);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        
        return ($row['count'] ?? 0) > 3;
    }
    
    private function getRiskLevel(float $riskScore): string {
        if ($riskScore >= 0.8) return 'CRITICAL';
        if ($riskScore >= 0.6) return 'HIGH';
        if ($riskScore >= 0.4) return 'MEDIUM';
        if ($riskScore >= 0.2) return 'LOW';
        return 'MINIMAL';
    }
    
    private function getRecommendation(float $riskScore): string {
        if ($riskScore >= 0.8) {
            return "BLOCK TRANSACTION - Immediate manual review required";
        } elseif ($riskScore >= 0.6) {
            return "FLAG FOR REVIEW - Additional verification needed";
        } elseif ($riskScore >= 0.4) {
            return "ENHANCED MONITORING - Verify supporting documents";
        } elseif ($riskScore >= 0.2) {
            return "STANDARD PROCESSING - Minor anomalies detected";
        }
        return "APPROVE - No significant risks detected";
    }
    
    private function logAnalysis(array $transaction, array $result): void {
        $sql = "INSERT INTO fraud_analysis_logs 
                (transaction_id, risk_score, risk_level, factors, recommendation, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())";
        
        $stmt = mysqli_prepare($this->conn, $sql);
        $factors = json_encode($result['factors']);
        mysqli_stmt_bind_param($stmt, 'idsss',
            $transaction['id'] ?? null,
            $result['risk_score'],
            $result['risk_level'],
            $factors,
            $result['recommendation']
        );
        
        mysqli_stmt_execute($stmt);
    }
    
    /**
     * Train fraud detection model with new cases
     */
    public function trainModel(array $cases): bool {
        foreach ($cases as $case) {
            $this->addToTrainingSet($case);
        }
        
        // Trigger model retraining
        $this->triggerModelUpdate();
        
        return true;
    }
    
    private function addToTrainingSet(array $case): void {
        $sql = "INSERT INTO fraud_training_data 
                (transaction_data, is_fraudulent, verified_by, verification_date)
                VALUES (?, ?, ?, NOW())";
        
        $stmt = mysqli_prepare($this->conn, $sql);
        $transactionData = json_encode($case['transaction']);
        $isFraudulent = $case['is_fraudulent'] ? 1 : 0;
        $verifiedBy = $case['verified_by'] ?? 'system';
        
        mysqli_stmt_bind_param($stmt, 'sis', 
            $transactionData, $isFraudulent, $verifiedBy);
        mysqli_stmt_execute($stmt);
    }
    
    /**
     * Get fraud statistics
     */
    public function getStatistics(string $period = '30 days'): array {
        $days = (int) str_replace(' days', '', $period);
        
        $sql = "SELECT 
                    COUNT(*) as total_transactions,
                    SUM(CASE WHEN risk_score >= 0.6 THEN 1 ELSE 0 END) as high_risk_count,
                    AVG(risk_score) as avg_risk_score,
                    DATE(created_at) as date
                FROM fraud_analysis_logs
                WHERE created_at > DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(created_at)
                ORDER BY date DESC";
        
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $days);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $stats = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $stats[] = $row;
        }
        
        return $stats;
    }
}
?>