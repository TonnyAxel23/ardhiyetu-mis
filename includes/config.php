<?php
// Database configuration for ArdhiYetu
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ardhiyetu');

// Site configuration
define('SITE_NAME', 'ArdhiYetu');
define('SITE_URL', 'http://localhost/ardhiyetu-mis/');
define('SITE_ROOT', dirname(dirname(__FILE__)));
// REMOVE THIS LINE: define('BASE_URL', SITE_URL); // This is duplicate

// Google APIs
define('GOOGLE_MAPS_API_KEY', 'YOUR_API_KEY_HERE');
define('GOOGLE_VISION_API_KEY', 'YOUR_GOOGLE_VISION_API_KEY');

// File upload configuration
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_TYPES', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx']);

// Session configuration
define('SESSION_TIMEOUT', 1800); // 30 minutes
define('SESSION_REGENERATE', 300); // Regenerate ID every 5 minutes

// Security
define('CSRF_TOKEN_LIFE', 3600); // 1 hour
define('PASSWORD_MIN_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes

// ============================================
// AI CONFIGURATION
// ============================================
define('AI_ENABLED', true);
define('AI_API_KEY', 'ardhiyetu_ai_' . md5(SITE_URL . '2024'));
define('AI_VALUATION_SERVICE_URL', 'http://localhost:5000');
define('AI_VISION_SERVICE_URL', 'http://localhost:5001');
define('AI_NLP_SERVICE_URL', 'http://localhost:5002');

// AI Model Paths
define('AI_MODEL_DIR', __DIR__ . '/../ardhiyetu-ai/models/');

// OCR Configuration
define('TESSERACT_PATH', '/usr/bin/tesseract'); // Linux path
// define('TESSERACT_PATH', 'C:\Program Files\Tesseract-OCR\tesseract.exe'); // Windows path

// Fraud Detection Thresholds
define('FRAUD_RISK_HIGH', 0.7);
define('FRAUD_RISK_MEDIUM', 0.4);
define('FRAUD_RISK_LOW', 0.2);

// AI Cache Settings
define('AI_CACHE_TTL', 3600); // 1 hour
define('AI_RATE_LIMIT', 100); // Requests per hour per user

// AI Email Notifications
define('AI_ALERT_EMAIL', 'ai-alerts@ardhiyetu.go.ke');

// ============================================
// BLOCKCHAIN CONFIGURATION
// ============================================
define('BLOCKCHAIN_ENABLED', true);
define('BLOCKCHAIN_NETWORK', 'testnet'); // testnet or mainnet

// Polygon Mumbai Testnet (Recommended for testing)
define('BLOCKCHAIN_RPC_URL', 'https://polygon-mumbai.infura.io/v3/YOUR_INFURA_KEY');
define('BLOCKCHAIN_WS_URL', 'wss://polygon-mumbai.infura.io/ws/v3/YOUR_INFURA_KEY');
define('BLOCKCHAIN_CHAIN_ID', 80001); // Polygon Mumbai testnet

// Smart Contract Addresses (Update after deployment)
define('LAND_REGISTRY_CONTRACT', '0x0000000000000000000000000000000000000000');
define('LAND_TOKEN_CONTRACT', '0x0000000000000000000000000000000000000000');
define('PAYMENT_CONTRACT', '0x0000000000000000000000000000000000000000');

// Wallet Configuration
define('BLOCKCHAIN_PRIVATE_KEY', 'YOUR_PRIVATE_KEY_HERE');
define('BLOCKCHAIN_GOVERNMENT_WALLET', '0x0000000000000000000000000000000000000000');

// IPFS Configuration
define('IPFS_GATEWAY_URL', 'https://ipfs.io/ipfs/');
define('IPFS_API_URL', 'https://ipfs.infura.io:5001');
define('IPFS_PROJECT_ID', 'YOUR_INFURA_PROJECT_ID');
define('IPFS_PROJECT_SECRET', 'YOUR_INFURA_SECRET');

// Gas Configuration
define('BLOCKCHAIN_GAS_LIMIT', 3000000);
define('BLOCKCHAIN_GAS_PRICE', '5000000000'); // 5 Gwei

// NFT Configuration
define('NFT_CONTRACT_URI', SITE_URL . 'api/nft/metadata/');
define('NFT_BASE_IMAGE_URI', SITE_URL . 'uploads/nfts/');

// Explorer URLs
define('BLOCKCHAIN_EXPLORER_URL', 'https://mumbai.polygonscan.com');
define('NFT_EXPLORER_URL', 'https://testnets.opensea.io');

// Web3 Provider Configuration
define('WEB3_PROVIDER_TIMEOUT', 30);
define('WEB3_MAX_RETRIES', 3);

// ============================================
// EMAIL CONFIGURATION
// ============================================
define('EMAIL_ENABLED', true);
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'noreply@ardhiyetu.go.ke');
define('SMTP_PASSWORD', 'YOUR_EMAIL_PASSWORD');
define('SMTP_SECURE', 'tls');

define('FROM_EMAIL', 'noreply@ardhiyetu.go.ke');
define('FROM_NAME', 'ArdhiYetu Land Management');
define('SUPPORT_EMAIL', 'support@ardhiyetu.go.ke');

// ============================================
// PAYMENT GATEWAY CONFIGURATION
// ============================================
define('PAYMENT_ENABLED', true);
define('MPESA_CONSUMER_KEY', 'YOUR_MPESA_CONSUMER_KEY');
define('MPESA_CONSUMER_SECRET', 'YOUR_MPESA_CONSUMER_SECRET');
define('MPESA_SHORTCODE', 'YOUR_MPESA_SHORTCODE');
define('MPESA_PASSKEY', 'YOUR_MPESA_PASSKEY');
define('MPESA_CALLBACK_URL', SITE_URL . 'api/mpesa/callback.php');

// ============================================
// UPLOAD PATHS
// ============================================
define('UPLOAD_PATH', SITE_ROOT . '/uploads/');
define('UPLOAD_URL', SITE_URL . 'uploads/');

// Specific upload directories
define('LAND_DOCUMENTS_PATH', UPLOAD_PATH . 'land_documents/');
define('USER_DOCUMENTS_PATH', UPLOAD_PATH . 'user_documents/');
define('TRANSFER_DOCUMENTS_PATH', UPLOAD_PATH . 'transfers/');
define('AI_DOCUMENTS_PATH', UPLOAD_PATH . 'ai_documents/');
define('NFT_IMAGES_PATH', UPLOAD_PATH . 'nfts/');
define('IPFS_CACHE_PATH', UPLOAD_PATH . 'ipfs/');

// ============================================
// CACHE CONFIGURATION
// ============================================
define('CACHE_ENABLED', true);
define('CACHE_TYPE', 'file');
define('CACHE_DIR', SITE_ROOT . '/cache/');
define('CACHE_TTL', 3600);

// Redis Configuration
define('REDIS_HOST', 'localhost');
define('REDIS_PORT', 6379);
define('REDIS_PASSWORD', '');
define('REDIS_DATABASE', 0);

// ============================================
// LOGGING CONFIGURATION
// ============================================
define('LOGGING_ENABLED', true);
define('LOG_LEVEL', 'DEBUG');
define('LOG_DIR', SITE_ROOT . '/logs/');
define('LOG_FILE', LOG_DIR . 'ardhiyetu_' . date('Y-m-d') . '.log');

// ============================================
// API CONFIGURATION
// ============================================
define('API_ENABLED', true);
define('API_RATE_LIMIT', 100);
define('API_KEY_EXPIRY', 2592000);

// JWT Configuration
define('JWT_SECRET', 'ardhiyetu_jwt_secret_' . md5(SITE_URL));
define('JWT_ALGORITHM', 'HS256');
define('JWT_EXPIRY', 3600);

// ============================================
// PERFORMANCE OPTIMIZATION
// ============================================
define('ENABLE_GZIP', true);
define('ENABLE_CACHING', true);
define('MINIFY_CSS_JS', false);
define('CDN_ENABLED', false);
define('CDN_URL', 'https://cdn.ardhiyetu.go.ke/');

// ============================================
// DEVELOPMENT vs PRODUCTION
// ============================================
define('ENVIRONMENT', 'development');

if (ENVIRONMENT === 'production') {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', LOG_DIR . 'php_errors.log');
    
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    
    if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != 'on') {
        header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
        exit();
    }
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
}

// ============================================
// DATABASE CONNECTION
// ============================================
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    if (LOGGING_ENABLED) {
        $error_msg = "Database connection failed: " . mysqli_connect_error();
        error_log($error_msg);
        file_put_contents(LOG_FILE, date('Y-m-d H:i:s') . " - " . $error_msg . "\n", FILE_APPEND);
    }
    
    if (ENVIRONMENT === 'production') {
        die("System is currently undergoing maintenance. Please try again later.");
    } else {
        die("Database connection failed: " . mysqli_connect_error());
    }
}

mysqli_set_charset($conn, 'utf8mb4');
date_default_timezone_set('Africa/Nairobi');

// ============================================
// AUTO-CREATE REQUIRED DIRECTORIES
// ============================================
$required_dirs = [
    UPLOAD_PATH,
    LAND_DOCUMENTS_PATH,
    USER_DOCUMENTS_PATH,
    TRANSFER_DOCUMENTS_PATH,
    AI_DOCUMENTS_PATH,
    NFT_IMAGES_PATH,
    IPFS_CACHE_PATH,
    LOG_DIR,
    CACHE_DIR,
    LOG_DIR . 'ai/',
    LOG_DIR . 'blockchain/',
    LOG_DIR . 'api/'
];

foreach ($required_dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// ============================================
// SECURITY FUNCTIONS
// ============================================
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token']) || time() > $_SESSION['csrf_token_expiry']) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_expiry'] = time() + CSRF_TOKEN_LIFE;
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        return false;
    }
    if (time() > $_SESSION['csrf_token_expiry']) {
        unset($_SESSION['csrf_token']);
        unset($_SESSION['csrf_token_expiry']);
        return false;
    }
    return true;
}

// ============================================
// LOGGING FUNCTION
// ============================================
function system_log($message, $level = 'INFO', $category = 'system') {
    if (!LOGGING_ENABLED) return;
    
    $log_levels = ['DEBUG' => 1, 'INFO' => 2, 'WARNING' => 3, 'ERROR' => 4];
    $current_level = $log_levels[strtoupper(LOG_LEVEL)] ?? 2;
    $message_level = $log_levels[strtoupper($level)] ?? 2;
    
    if ($message_level >= $current_level) {
        $log_entry = sprintf(
            "[%s] [%s] [%s] %s\n",
            date('Y-m-d H:i:s'),
            $level,
            $category,
            $message
        );
        
        $log_file = LOG_DIR . $category . '_' . date('Y-m-d') . '.log';
        file_put_contents($log_file, $log_entry, FILE_APPEND);
    }
}

system_log('System initialized', 'INFO', 'system');

// ============================================
// SESSION CONFIGURATION
// ============================================
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_TIMEOUT,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => (ENVIRONMENT === 'production'),
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    
    session_start();
}

if (!isset($_SESSION['last_regeneration']) || 
    time() - $_SESSION['last_regeneration'] > SESSION_REGENERATE) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

if (isset($_SESSION['last_activity']) && 
    (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['timeout_message'] = 'Your session has expired. Please login again.';
    header('Location: ' . SITE_URL . 'auth/login.php');
    exit();
}
$_SESSION['last_activity'] = time();

// ============================================
// CORS HEADERS FOR API
// ============================================
if (isset($_SERVER['HTTP_ORIGIN'])) {
    $allowed_origins = [
        'http://localhost:3000',
        'http://localhost:8080',
        'https://ardhiyetu.go.ke',
        'https://www.ardhiyetu.go.ke'
    ];
    
    if (in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
        header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    }
    
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
        header('Access-Control-Allow-Headers: ' . $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']);
    }
    
    exit(0);
}

// ============================================
// HELPER FUNCTIONS
// ============================================
function sanitize_input_config($data) {
    if (is_array($data)) {
        return array_map('sanitize_input_config', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function format_date_config($date, $format = 'F j, Y, g:i a') {
    return date($format, strtotime($date));
}

function format_currency_config($amount) {
    return 'KSh ' . number_format($amount, 2);
}

function redirect_url($url, $statusCode = 303) {
    header('Location: ' . $url, true, $statusCode);
    exit();
}

// ============================================
// ERROR HANDLER
// ============================================
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    $error_types = [
        E_ERROR => 'ERROR',
        E_WARNING => 'WARNING',
        E_PARSE => 'PARSE',
        E_NOTICE => 'NOTICE',
        E_CORE_ERROR => 'CORE_ERROR',
        E_CORE_WARNING => 'CORE_WARNING',
        E_COMPILE_ERROR => 'COMPILE_ERROR',
        E_COMPILE_WARNING => 'COMPILE_WARNING',
        E_USER_ERROR => 'USER_ERROR',
        E_USER_WARNING => 'USER_WARNING',
        E_USER_NOTICE => 'USER_NOTICE',
        E_STRICT => 'STRICT',
        E_RECOVERABLE_ERROR => 'RECOVERABLE_ERROR',
        E_DEPRECATED => 'DEPRECATED',
        E_USER_DEPRECATED => 'USER_DEPRECATED'
    ];
    
    $error_type = $error_types[$errno] ?? 'UNKNOWN';
    
    system_log(
        sprintf("PHP %s: %s in %s on line %d", 
            $error_type, 
            $errstr, 
            $errfile, 
            $errline
        ),
        'ERROR',
        'php_errors'
    );
    
    return true;
});

// ============================================
// SHUTDOWN HANDLER
// ============================================
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        system_log(
            sprintf("Fatal error: %s in %s on line %d", 
                $error['message'], 
                $error['file'], 
                $error['line']
            ),
            'ERROR',
            'fatal_errors'
        );
        
        if (ENVIRONMENT === 'production') {
            mail(
                AI_ALERT_EMAIL,
                'Fatal Error on ' . SITE_NAME,
                "A fatal error occurred:\n\n" . print_r($error, true)
            );
        }
    }
});

// ============================================
// GLOBAL VARIABLES
// ============================================
$GLOBALS['conn'] = $conn;
$GLOBALS['site_url'] = SITE_URL;
$GLOBALS['site_name'] = SITE_NAME;
$GLOBALS['base_url'] = SITE_URL; // Use SITE_URL as base_url

// Initialize CSRF token
if (!isset($_SESSION['csrf_token'])) {
    generate_csrf_token();
}

system_log('Configuration loaded successfully', 'INFO', 'config');
?>