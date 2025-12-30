<?php
namespace ArdhiYetu\AI;

class LandRecommendationAI {
    private $conn;
    
    public function __construct($conn = null) {
        $this->conn = $conn ?? $GLOBALS['conn'];
    }
    
    /**
     * Get personalized land recommendations
     */
    public function getRecommendations(int $userId, array $preferences = []): array {
        $userProfile = $this->getUserProfile($userId);
        $recommendations = [];
        
        // 1. Collaborative Filtering (users similar to you liked...)
        $collabRecommendations = $this->collaborativeFiltering($userId, $userProfile);
        $recommendations = array_merge($recommendations, $collabRecommendations);
        
        // 2. Content-Based Filtering (based on your preferences)
        $contentRecommendations = $this->contentBasedFiltering($preferences);
        $recommendations = array_merge($recommendations, $contentRecommendations);
        
        // 3. Trending Properties (popular now)
        $trendingRecommendations = $this->getTrendingProperties($userProfile['location_preference']);
        $recommendations = array_merge($recommendations, $trendingRecommendations);
        
        // 4. Price-Based Recommendations (within budget)
        $priceRecommendations = $this->getPriceBasedRecommendations($userProfile['budget']);
        $recommendations = array_merge($recommendations, $priceRecommendations);
        
        // Remove duplicates and rank
        $rankedRecommendations = $this->rankRecommendations($recommendations, $userProfile);
        
        return array_slice($rankedRecommendations, 0, 10); // Top 10
    }
    
    private function getUserProfile(int $userId): array {
        $sql = "SELECT 
                    u.user_id, u.name, u.email,
                    COALESCE(MAX(l.price), 0) as max_price_viewed,
                    GROUP_CONCAT(DISTINCT l.location) as viewed_locations,
                    GROUP_CONCAT(DISTINCT l.land_type) as viewed_types,
                    AVG(l.size) as avg_size_viewed,
                    COUNT(DISTINCT l.record_id) as total_views,
                    COUNT(DISTINCT t.transfer_id) as transactions_completed
                FROM users u
                LEFT JOIN user_activity_logs ual ON u.user_id = ual.user_id 
                    AND ual.activity_type = 'view_land'
                LEFT JOIN land_records l ON ual.entity_id = l.record_id
                LEFT JOIN ownership_transfers t ON u.user_id = t.from_user_id 
                    AND t.status = 'completed'
                WHERE u.user_id = ?
                GROUP BY u.user_id";
        
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $profile = mysqli_fetch_assoc($result) ?? [];
        
        // Enhance with preferences
        $profile['budget'] = $this->estimateBudget($profile);
        $profile['location_preference'] = $this->extractLocationPreference($profile);
        $profile['property_type_preference'] = $this->extractPropertyTypePreference($profile);
        
        return $profile;
    }
    
    private function collaborativeFiltering(int $userId, array $userProfile): array {
        // Find similar users
        $similarUsers = $this->findSimilarUsers($userId);
        
        if (empty($similarUsers)) {
            return [];
        }
        
        // Get lands liked by similar users
        $userIds = implode(',', $similarUsers);
        $sql = "SELECT DISTINCT l.*, 
                       COUNT(DISTINCT ual.user_id) as similar_user_count,
                       AVG(ual.rating) as avg_rating
                FROM land_records l
                JOIN user_activity_logs ual ON l.record_id = ual.entity_id
                WHERE ual.activity_type IN ('view_land', 'save_land', 'make_offer')
                AND ual.user_id IN ($userIds)
                AND l.status = 'available'
                AND l.record_id NOT IN (
                    SELECT entity_id FROM user_activity_logs 
                    WHERE user_id = ? AND activity_type = 'view_land'
                )
                GROUP BY l.record_id
                HAVING similar_user_count >= 2
                ORDER BY similar_user_count DESC, avg_rating DESC
                LIMIT 20";
        
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $recommendations = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $recommendations[] = [
                'land' => $row,
                'score' => $this->calculateCollaborativeScore($row, $similarUsers),
                'reason' => "Popular among users similar to you"
            ];
        }
        
        return $recommendations;
    }
    
    private function findSimilarUsers(int $userId): array {
        $sql = "SELECT u2.user_id,
                       COUNT(DISTINCT ual1.entity_id) as common_interests,
                       COUNT(DISTINCT ual2.entity_id) as total_interests
                FROM users u1
                JOIN user_activity_logs ual1 ON u1.user_id = ual1.user_id
                JOIN user_activity_logs ual2 ON ual1.entity_id = ual2.entity_id 
                    AND ual1.activity_type = ual2.activity_type
                JOIN users u2 ON ual2.user_id = u2.user_id
                WHERE u1.user_id = ?
                AND u2.user_id != ?
                AND ual1.activity_type IN ('view_land', 'save_land')
                GROUP BY u2.user_id
                HAVING common_interests >= 3
                AND (common_interests / total_interests) >= 0.3
                ORDER BY common_interests DESC
                LIMIT 10";
        
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ii', $userId, $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $similarUsers = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $similarUsers[] = $row['user_id'];
        }
        
        return $similarUsers;
    }
    
    private function contentBasedFiltering(array $preferences): array {
        if (empty($preferences)) {
            return [];
        }
        
        $conditions = [];
        $params = [];
        $types = '';
        
        if (!empty($preferences['location'])) {
            $conditions[] = "location LIKE ?";
            $params[] = "%{$preferences['location']}%";
            $types .= 's';
        }
        
        if (!empty($preferences['min_price']) && !empty($preferences['max_price'])) {
            $conditions[] = "estimated_value BETWEEN ? AND ?";
            $params[] = $preferences['min_price'];
            $params[] = $preferences['max_price'];
            $types .= 'dd';
        }
        
        if (!empty($preferences['land_type'])) {
            $conditions[] = "land_type = ?";
            $params[] = $preferences['land_type'];
            $types .= 's';
        }
        
        if (!empty($preferences['min_size']) && !empty($preferences['max_size'])) {
            $conditions[] = "size BETWEEN ? AND ?";
            $params[] = $preferences['min_size'];
            $params[] = $preferences['max_size'];
            $types .= 'dd';
        }
        
        if (empty($conditions)) {
            return [];
        }
        
        $whereClause = implode(' AND ', $conditions);
        $sql = "SELECT * FROM land_records 
                WHERE status = 'available' 
                AND $whereClause
                ORDER BY registered_at DESC
                LIMIT 20";
        
        $stmt = mysqli_prepare($this->conn, $sql);
        if ($types) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $recommendations = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $matchScore = $this->calculateContentMatchScore($row, $preferences);
            if ($matchScore > 0.5) {
                $recommendations[] = [
                    'land' => $row,
                    'score' => $matchScore,
                    'reason' => "Matches your specified preferences"
                ];
            }
        }
        
        return $recommendations;
    }
    
    private function getTrendingProperties(string $location = ''): array {
        $locationCondition = $location ? "AND location LIKE ?" : "";
        $params = [];
        $types = '';
        
        if ($location) {
            $params[] = "%$location%";
            $types .= 's';
        }
        
        $sql = "SELECT l.*,
                       COUNT(DISTINCT ual.user_id) as view_count,
                       COUNT(DISTINCT CASE 
                           WHEN ual.activity_type = 'save_land' THEN ual.user_id 
                       END) as save_count,
                       COUNT(DISTINCT ot.transfer_id) as offer_count
                FROM land_records l
                LEFT JOIN user_activity_logs ual ON l.record_id = ual.entity_id
                    AND ual.activity_type IN ('view_land', 'save_land')
                    AND ual.activity_date > DATE_SUB(NOW(), INTERVAL 7 DAY)
                LEFT JOIN ownership_transfers ot ON l.record_id = ot.record_id
                    AND ot.status = 'submitted'
                    AND ot.submitted_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
                WHERE l.status = 'available'
                $locationCondition
                GROUP BY l.record_id
                HAVING view_count > 5
                ORDER BY (view_count * 0.5 + save_count * 1.5 + offer_count * 2) DESC
                LIMIT 15";
        
        $stmt = mysqli_prepare($this->conn, $sql);
        if ($types) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $recommendations = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $popularityScore = $this->calculatePopularityScore($row);
            $recommendations[] = [
                'land' => $row,
                'score' => $popularityScore,
                'reason' => "Trending in " . ($location ?: "your area")
            ];
        }
        
        return $recommendations;
    }
    
    private function getPriceBasedRecommendations(float $budget): array {
        if ($budget <= 0) {
            return [];
        }
        
        $minBudget = $budget * 0.7;
        $maxBudget = $budget * 1.3;
        
        $sql = "SELECT * FROM land_records
                WHERE status = 'available'
                AND estimated_value BETWEEN ? AND ?
                ORDER BY ABS(estimated_value - ?)
                LIMIT 15";
        
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ddd', $minBudget, $maxBudget, $budget);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $recommendations = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $priceScore = $this->calculatePriceScore($row['estimated_value'], $budget);
            $recommendations[] = [
                'land' => $row,
                'score' => $priceScore,
                'reason' => "Within your budget range"
            ];
        }
        
        return $recommendations;
    }
    
    private function rankRecommendations(array $recommendations, array $userProfile): array {
        // Remove duplicates
        $uniqueRecommendations = [];
        $seenIds = [];
        
        foreach ($recommendations as $rec) {
            $landId = $rec['land']['record_id'];
            if (!in_array($landId, $seenIds)) {
                $seenIds[] = $landId;
                
                // Apply additional scoring
                $rec['final_score'] = $this->calculateFinalScore($rec, $userProfile);
                $uniqueRecommendations[] = $rec;
            }
        }
        
        // Sort by final score
        usort($uniqueRecommendations, function($a, $b) {
            return $b['final_score'] <=> $a['final_score'];
        });
        
        return $uniqueRecommendations;
    }
    
    private function calculateFinalScore(array $recommendation, array $userProfile): float {
        $baseScore = $recommendation['score'] ?? 0.5;
        
        // Location preference bonus
        $locationBonus = $this->calculateLocationBonus(
            $recommendation['land']['location'],
            $userProfile['location_preference']
        );
        
        // Price suitability bonus
        $priceBonus = $this->calculatePriceBonus(
            $recommendation['land']['estimated_value'],
            $userProfile['budget']
        );
        
        // Recency bonus (newer listings)
        $recencyBonus = $this->calculateRecencyBonus(
            $recommendation['land']['registered_at']
        );
        
        // Quality bonus (complete information)
        $qualityBonus = $this->calculateQualityBonus($recommendation['land']);
        
        // Calculate weighted final score
        $finalScore = (
            $baseScore * 0.4 +
            $locationBonus * 0.2 +
            $priceBonus * 0.2 +
            $recencyBonus * 0.1 +
            $qualityBonus * 0.1
        );
        
        return min(1.0, $finalScore);
    }
    
    private function calculateLocationBonus(string $landLocation, array $userLocations): float {
        if (empty($userLocations)) return 0.3;
        
        foreach ($userLocations as $userLocation) {
            similar_text(strtolower($landLocation), strtolower($userLocation), $percent);
            if ($percent > 70) {
                return 0.8;
            }
        }
        
        return 0.2;
    }
    
    private function calculatePriceBonus(float $landPrice, float $userBudget): float {
        if ($userBudget <= 0) return 0.5;
        
        $ratio = $landPrice / $userBudget;
        
        if ($ratio >= 0.9 && $ratio <= 1.1) return 0.9; // Perfect match
        if ($ratio >= 0.7 && $ratio <= 1.3) return 0.7; // Good match
        if ($ratio >= 0.5 && $ratio <= 1.5) return 0.5; // Acceptable
        
        return 0.2; // Poor match
    }
    
    private function calculateRecencyBonus(string $registeredDate): float {
        $daysOld = (time() - strtotime($registeredDate)) / (60 * 60 * 24);
        
        if ($daysOld <= 7) return 0.9; // Less than a week
        if ($daysOld <= 30) return 0.7; // Less than a month
        if ($daysOld <= 90) return 0.5; // Less than 3 months
        
        return 0.2; // Older
    }
    
    private function calculateQualityBonus(array $landData): float {
        $completeness = 0;
        
        // Check for images
        if (!empty($landData['images'])) $completeness += 0.2;
        
        // Check for description
        if (!empty($landData['description']) && strlen($landData['description']) > 50) {
            $completeness += 0.2;
        }
        
        // Check for documents
        if (!empty($landData['document_path'])) $completeness += 0.2;
        
        // Check for coordinates
        if (!empty($landData['latitude']) && !empty($landData['longitude'])) {
            $completeness += 0.2;
        }
        
        // Check for survey details
        if (!empty($landData['survey_details'])) $completeness += 0.2;
        
        return $completeness;
    }
    
    /**
     * Get recommendation explanation
     */
    public function explainRecommendation(array $recommendation, array $userProfile): string {
        $reasons = [];
        
        if ($recommendation['score'] > 0.8) {
            $reasons[] = "Highly relevant based on your profile";
        }
        
        if ($this->calculateLocationBonus(
            $recommendation['land']['location'],
            $userProfile['location_preference']
        ) > 0.7) {
            $reasons[] = "Located in your preferred area";
        }
        
        if ($this->calculatePriceBonus(
            $recommendation['land']['estimated_value'],
            $userProfile['budget']
        ) > 0.7) {
            $reasons[] = "Fits your budget perfectly";
        }
        
        if (!empty($recommendation['reason'])) {
            $reasons[] = $recommendation['reason'];
        }
        
        return implode('. ', $reasons);
    }
}
?>