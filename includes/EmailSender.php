<?php
// includes/EmailSender.php

// Define multiple possible PHPMailer paths
$possiblePaths = [
    __DIR__ . '/phpmailer/src/',
    __DIR__ . '/../phpmailer/src/',
    __DIR__ . '/../../phpmailer/src/',
    __DIR__ . '/../vendor/phpmailer/phpmailer/src/',
    __DIR__ . '/../../vendor/phpmailer/phpmailer/src/',
    'C:/xampp/htdocs/ardhiyetu-mis/includes/phpmailer/src/',
    'C:/xampp/htdocs/ardhiyetu-mis/vendor/phpmailer/phpmailer/src/',
];

$phpmailerPath = null;
foreach ($possiblePaths as $path) {
    if (file_exists($path . 'PHPMailer.php')) {
        $phpmailerPath = $path;
        break;
    }
}

if (!$phpmailerPath) {
    die('PHPMailer not found. Please install it using: composer require phpmailer/phpmailer');
}

define('PHPMailer_PATH', $phpmailerPath);

// Load PHPMailer classes manually
require_once PHPMailer_PATH . 'Exception.php';
require_once PHPMailer_PATH . 'PHPMailer.php';
require_once PHPMailer_PATH . 'SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailSender {
    private $mailer;
    private $templatePath;
    
    // Configuration - Update these values!
    private $config = [
        'smtp_host' => 'smtp.gmail.com',
        'smtp_port' => 587,
        'smtp_username' => 'tonnyodhiambo49@gmail.com', // CHANGE THIS
        'smtp_password' => 'mjjc bhkp lfuv igth',    // CHANGE THIS
        'smtp_secure' => 'tls',
        'email_from' => 'tonnyodhiambo707@gmail.com',
        'email_from_name' => 'ArdhiYetu',
        'admin_email' => 'tonnyodhiambo707@gmail.com',
        'site_name' => 'ArdhiYetu',
        'debug' => true  // Set to false in production
    ];
    
    public function __construct($customConfig = []) {
        // Merge custom config if provided
        if (!empty($customConfig)) {
            $this->config = array_merge($this->config, $customConfig);
        }
        
        $this->mailer = new PHPMailer(true);
        $this->templatePath = __DIR__ . '/email-templates/';
        
        // Check if template directory exists
        if (!is_dir($this->templatePath)) {
            error_log("Warning: Email template directory not found: " . $this->templatePath);
        }
        
        $this->setupSMTP();
    }
    
    private function setupSMTP() {
        try {
            // Server settings
            $this->mailer->isSMTP();
            $this->mailer->Host       = $this->config['smtp_host'];
            $this->mailer->SMTPAuth   = true;
            $this->mailer->Username   = $this->config['smtp_username'];
            $this->mailer->Password   = $this->config['smtp_password'];
            $this->mailer->SMTPSecure = $this->config['smtp_secure'];
            $this->mailer->Port       = $this->config['smtp_port'];
            
            // Debug mode
            if ($this->config['debug']) {
                $this->mailer->SMTPDebug = 2;
                $this->mailer->Debugoutput = function($str, $level) {
                    error_log("PHPMailer Debug: $str");
                    echo "PHPMailer: $str<br>";
                };
            }
            
            // Default from address
            $this->mailer->setFrom(
                $this->config['email_from'], 
                $this->config['email_from_name']
            );
            
        } catch (Exception $e) {
            $error = "Email configuration error: " . $e->getMessage();
            error_log($error);
            throw new Exception($error);
        }
    }
    
    private function renderTemplate($templateName, $data) {
        $templateFile = $this->templatePath . $templateName . '.php';
        
        if (!file_exists($templateFile)) {
            $error = "Email template not found: $templateFile";
            error_log($error);
            throw new Exception($error);
        }
        
        // Extract variables for template
        extract($data);
        
        // Start output buffering
        ob_start();
        
        // Include the template file
        include $templateFile;
        
        // Get the contents and clean buffer
        $content = ob_get_clean();
        
        return $content;
    }
    
    private function getSubjectFromTemplate($content) {
        // Extract subject from <title> tag
        if (preg_match('/<title>(.*?)<\/title>/', $content, $matches)) {
            return trim($matches[1]);
        }
        return 'Land Transfer Notification';
    }
    
    private function validateEmailData($data, $requiredFields = ['user_email', 'user_name']) {
        $missingFields = [];
        
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $missingFields[] = $field;
            }
        }
        
        if (!empty($missingFields)) {
            $error = "Missing required fields: " . implode(', ', $missingFields);
            error_log("Email validation error: $error");
            throw new Exception($error);
        }
        
        // Validate email format
        if (!filter_var($data['user_email'], FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format: " . $data['user_email'];
            error_log("Email validation error: $error");
            throw new Exception($error);
        }
        
        return true;
    }
    
    public function sendEmail($toEmail, $toName, $templateName, $data) {
        try {
            // Validate input data
            $this->validateEmailData(array_merge($data, [
                'user_email' => $toEmail,
                'user_name' => $toName
            ]));
            
            // Clear previous recipients
            $this->mailer->clearAddresses();
            $this->mailer->clearCCs();
            $this->mailer->clearBCCs();
            $this->mailer->clearAttachments();
            $this->mailer->clearReplyTos();
            $this->mailer->clearCustomHeaders();
            
            // Add recipient
            $this->mailer->addAddress($toEmail, $toName);
            
            // Ensure site_name is in data
            if (!isset($data['site_name'])) {
                $data['site_name'] = $this->config['site_name'];
            }
            if (!isset($data['admin_email'])) {
                $data['admin_email'] = $this->config['admin_email'];
            }
            
            // Render template
            $emailBody = $this->renderTemplate($templateName, $data);
            
            // Get subject
            $subject = $this->getSubjectFromTemplate($emailBody);
            
            // Email content
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $subject;
            $this->mailer->Body    = $emailBody;
            
            // Create plain text version
            $plainText = strip_tags($emailBody);
            $plainText = preg_replace('/\s+/', ' ', $plainText);
            $plainText = preg_replace('/\n\s*\n+/', "\n\n", $plainText);
            $this->mailer->AltBody = $plainText;
            
            // Add CC to admin for certain emails
            if (in_array($templateName, ['transfer_submission_admin', 'transfer_approved_new', 'transfer_rejected'])) {
                $this->mailer->addCC($this->config['admin_email'], 'Administrator');
            }
            
            // Send email
            if ($this->mailer->send()) {
                error_log("✓ Email sent successfully to: $toEmail ($toName)");
                return true;
            } else {
                error_log("✗ Failed to send email to: $toEmail - " . $this->mailer->ErrorInfo);
                return false;
            }
            
        } catch (Exception $e) {
            $error = "Email sending failed to $toEmail: " . $e->getMessage();
            error_log($error);
            throw new Exception($error);
        }
    }
    
    // Convenience methods with validation
    
    public function sendTransferApprovedPrevious($data) {
        $this->validateEmailData($data);
        return $this->sendEmail(
            $data['user_email'],
            $data['user_name'],
            'transfer_approved_previous',
            $data
        );
    }
    
    public function sendTransferApprovedNew($data) {
        $this->validateEmailData($data);
        return $this->sendEmail(
            $data['user_email'],
            $data['user_name'],
            'transfer_approved_new',
            $data
        );
    }
    
    public function sendTransferSubmission($data) {
        $this->validateEmailData($data);
        return $this->sendEmail(
            $data['user_email'],
            $data['user_name'],
            'transfer_submission',
            $data
        );
    }
    
    public function sendTransferUnderReview($data) {
        $this->validateEmailData($data);
        return $this->sendEmail(
            $data['user_email'],
            $data['user_name'],
            'transfer_under_review',
            $data
        );
    }
    
    public function sendTransferRejected($data) {
        $this->validateEmailData($data);
        return $this->sendEmail(
            $data['user_email'],
            $data['user_name'],
            'transfer_rejected',
            $data
        );
    }
    
    public function sendTransferSubmissionAdmin($data) {
        // For admin notifications, we still need user_email for the data
        if (!isset($data['user_email'])) {
            $data['user_email'] = 'unknown@example.com';
        }
        if (!isset($data['user_name'])) {
            $data['user_name'] = 'Unknown User';
        }
        
        return $this->sendEmail(
            $this->config['admin_email'],
            'Administrator',
            'transfer_submission_admin',
            $data
        );
    }
    
    // Generic email sending method
    public function sendCustomEmail($toEmail, $toName, $templateName, $data = []) {
        $data['user_email'] = $toEmail;
        $data['user_name'] = $toName;
        return $this->sendEmail($toEmail, $toName, $templateName, $data);
    }
    
    // Test email connection
    public function testConnection() {
        try {
            $this->mailer->smtpConnect();
            return true;
        } catch (Exception $e) {
            error_log("SMTP Connection Test Failed: " . $e->getMessage());
            return false;
        }
    }
    
    // Get last error
    public function getLastError() {
        return $this->mailer->ErrorInfo;
    }
    
    // Get configuration (for debugging)
    public function getConfig() {
        $config = $this->config;
        // Hide password for security
        $config['smtp_password'] = '********';
        return $config;
    }
}
?>