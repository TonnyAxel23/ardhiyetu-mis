-- AI Predictions Table
CREATE TABLE ai_predictions (
    prediction_id INT PRIMARY KEY AUTO_INCREMENT,
    record_id INT,
    user_id INT,
    prediction_type ENUM('valuation', 'fraud_risk', 'recommendation', 'document_analysis') NOT NULL,
    input_data JSON,
    output_data JSON,
    model_version VARCHAR(50),
    confidence_score DECIMAL(5,4),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_prediction_type (prediction_type),
    INDEX idx_record_id (record_id),
    INDEX idx_user_id (user_id),
    FOREIGN KEY (record_id) REFERENCES land_records(record_id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- ML Training Data
CREATE TABLE ml_training_data (
    data_id INT PRIMARY KEY AUTO_INCREMENT,
    features JSON NOT NULL,
    target_value DECIMAL(15,2),
    source VARCHAR(50) DEFAULT 'manual',
    verified BOOLEAN DEFAULT FALSE,
    verified_by INT,
    verification_date TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_source (source),
    INDEX idx_verified (verified),
    FOREIGN KEY (verified_by) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Fraud Analysis Logs
CREATE TABLE fraud_analysis_logs (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    transaction_id INT,
    risk_score DECIMAL(5,4) NOT NULL,
    risk_level ENUM('MINIMAL', 'LOW', 'MEDIUM', 'HIGH', 'CRITICAL') NOT NULL,
    factors JSON,
    recommendation TEXT,
    reviewed_by INT,
    review_date TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_transaction_id (transaction_id),
    INDEX idx_risk_level (risk_level),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (transaction_id) REFERENCES ownership_transfers(transfer_id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Fraud Watchlist
CREATE TABLE fraud_watchlist (
    watchlist_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    reason TEXT NOT NULL,
    added_by INT NOT NULL,
    added_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    INDEX idx_user_id (user_id),
    INDEX idx_is_active (is_active),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (added_by) REFERENCES users(user_id) ON DELETE CASCADE
);

-- User Behavior Logs for ML
CREATE TABLE user_behavior_logs (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    session_id VARCHAR(100),
    activity_type VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    activity_data JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_activity (user_id, activity_type),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- AI Model Versions
CREATE TABLE ai_model_versions (
    model_id INT PRIMARY KEY AUTO_INCREMENT,
    model_name VARCHAR(100) NOT NULL,
    version VARCHAR(50) NOT NULL,
    file_path VARCHAR(500),
    accuracy_score DECIMAL(5,4),
    training_data_size INT,
    trained_at TIMESTAMP NULL,
    deployed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    parameters JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_model_version (model_name, version)
);

-- Historical Transactions for Training
CREATE TABLE historical_transactions (
    transaction_id INT PRIMARY KEY AUTO_INCREMENT,
    parcel_no VARCHAR(100) NOT NULL,
    location VARCHAR(255) NOT NULL,
    county VARCHAR(100),
    size DECIMAL(10,2) NOT NULL,
    price DECIMAL(15,2) NOT NULL,
    price_per_acre DECIMAL(15,2) GENERATED ALWAYS AS (price / size) STORED,
    transaction_type ENUM('sale', 'lease', 'gift', 'inheritance') NOT NULL,
    transaction_date DATE NOT NULL,
    seller_type ENUM('individual', 'company', 'government') DEFAULT 'individual',
    buyer_type ENUM('individual', 'company', 'government') DEFAULT 'individual',
    source VARCHAR(100) DEFAULT 'manual',
    verified BOOLEAN DEFAULT FALSE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_location (location),
    INDEX idx_county (county),
    INDEX idx_transaction_date (transaction_date),
    INDEX idx_verified (verified)
);

-- Document Analysis Results
CREATE TABLE document_analysis_results (
    analysis_id INT PRIMARY KEY AUTO_INCREMENT,
    document_id INT,
    document_type VARCHAR(50) NOT NULL,
    extracted_data JSON,
    verification_results JSON,
    anomalies JSON,
    confidence_score DECIMAL(5,4),
    processed_by VARCHAR(50) DEFAULT 'ai',
    processing_time_ms INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_document_id (document_id),
    INDEX idx_document_type (document_type),
    INDEX idx_confidence (confidence_score)
);

-- Land Recommendation Cache
CREATE TABLE land_recommendation_cache (
    cache_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    recommendations JSON NOT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    hits INT DEFAULT 0,
    last_accessed TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- AI Service Logs
CREATE TABLE ai_service_logs (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    service_name VARCHAR(100) NOT NULL,
    endpoint VARCHAR(255),
    request_data JSON,
    response_data JSON,
    response_time_ms INT,
    status_code INT,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_service_name (service_name),
    INDEX idx_created_at (created_at)
);

-- Create stored procedure for AI data aggregation
DELIMITER $$
CREATE PROCEDURE sp_aggregate_ai_data(
    IN p_start_date DATE,
    IN p_end_date DATE
)
BEGIN
    -- Aggregate fraud detection statistics
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as total_analyses,
        AVG(risk_score) as avg_risk_score,
        SUM(CASE WHEN risk_level = 'CRITICAL' THEN 1 ELSE 0 END) as critical_count,
        SUM(CASE WHEN risk_level = 'HIGH' THEN 1 ELSE 0 END) as high_count,
        SUM(CASE WHEN risk_level IN ('CRITICAL', 'HIGH') THEN 1 ELSE 0 END) as high_risk_total
    FROM fraud_analysis_logs
    WHERE DATE(created_at) BETWEEN p_start_date AND p_end_date
    GROUP BY DATE(created_at)
    ORDER BY date;
    
    -- Aggregate AI prediction accuracy
    SELECT 
        prediction_type,
        COUNT(*) as total_predictions,
        AVG(confidence_score) as avg_confidence,
        MIN(confidence_score) as min_confidence,
        MAX(confidence_score) as max_confidence
    FROM ai_predictions
    WHERE DATE(created_at) BETWEEN p_start_date AND p_end_date
    GROUP BY prediction_type;
    
    -- Document analysis statistics
    SELECT 
        document_type,
        COUNT(*) as total_analyses,
        AVG(confidence_score) as avg_confidence,
        SUM(CASE WHEN JSON_LENGTH(anomalies) > 0 THEN 1 ELSE 0 END) as anomalies_detected
    FROM document_analysis_results
    WHERE DATE(created_at) BETWEEN p_start_date AND p_end_date
    GROUP BY document_type;
END$$
DELIMITER ;

-- Create trigger for automatic fraud flagging
DELIMITER $$
CREATE TRIGGER tr_flag_high_risk_transactions
AFTER INSERT ON fraud_analysis_logs
FOR EACH ROW
BEGIN
    IF NEW.risk_level IN ('CRITICAL', 'HIGH') THEN
        -- Create notification for admins
        INSERT INTO notifications 
        (user_id, title, message, type, related_entity_type, related_entity_id, priority)
        SELECT 
            user_id,
            'High Risk Transaction Detected',
            CONCAT('Transaction #', NEW.transaction_id, ' has been flagged as ', NEW.risk_level, ' risk'),
            'warning',
            'transfer',
            NEW.transaction_id,
            'high'
        FROM users 
        WHERE role = 'admin' AND is_active = 1;
        
        -- Update transfer status if critical
        IF NEW.risk_level = 'CRITICAL' THEN
            UPDATE ownership_transfers 
            SET status = 'flagged_for_review',
                review_reason = 'AI Fraud Detection'
            WHERE transfer_id = NEW.transaction_id;
        END IF;
    END IF;
END$$
DELIMITER ;

-- Create view for AI dashboard
CREATE VIEW vw_ai_dashboard AS
SELECT 
    DATE(fal.created_at) as date,
    COUNT(DISTINCT fal.transaction_id) as fraud_checks,
    AVG(fal.risk_score) as avg_fraud_risk,
    COUNT(DISTINCT ap.prediction_id) as ai_predictions,
    AVG(ap.confidence_score) as avg_prediction_confidence,
    COUNT(DISTINCT dar.analysis_id) as document_analyses,
    AVG(dar.confidence_score) as avg_document_confidence
FROM fraud_analysis_logs fal
LEFT JOIN ai_predictions ap ON DATE(ap.created_at) = DATE(fal.created_at)
LEFT JOIN document_analysis_results dar ON DATE(dar.created_at) = DATE(fal.created_at)
WHERE fal.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(fal.created_at)
ORDER BY date DESC;