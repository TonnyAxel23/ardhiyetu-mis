<?php
// Core initialization file for ArdhiYetu
// Include this file in all PHP scripts

// Check if we're already in the includes directory
$current_dir = dirname(__FILE__);
$includes_dir = $current_dir;

// Set the base path
define('INCLUDES_PATH', $includes_dir . DIRECTORY_SEPARATOR);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration
require_once INCLUDES_PATH . 'config.php';

// Include functions
require_once INCLUDES_PATH . 'functions.php';

// Set default timezone
date_default_timezone_set('Africa/Nairobi');

// Check if user is logged in (for pages that require authentication)
function require_login() {
    if (!is_logged_in()) {
        flash_message('danger', 'Please login to access this page.');
        redirect('auth/login.php');
    }
}

// Check if user is admin
function require_admin() {
    require_login();
    if (!is_admin()) {
        flash_message('danger', 'Access denied. Admin privileges required.');
        redirect('index.php');
    }
}

// Check if user is officer or admin
function require_officer() {
    require_login();
    if (!is_officer()) {
        flash_message('danger', 'Access denied. Officer privileges required.');
        redirect('index.php');
    }
}

// Generate CSRF token for forms
function csrf_token() {
    echo '<input type="hidden" name="csrf_token" value="' . generate_csrf_token() . '">';
}

// Validate CSRF token from form submission
function validate_csrf() {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        flash_message('danger', 'Invalid CSRF token. Please try again.');
        redirect($_SERVER['HTTP_REFERER'] ?? 'index.php');
    }
}

// Sanitize all POST data
function sanitize_post() {
    foreach ($_POST as $key => $value) {
        if (is_string($value)) {
            $_POST[$key] = sanitize_input($value);
        }
    }
}

// Sanitize all GET data
function sanitize_get() {
    foreach ($_GET as $key => $value) {
        if (is_string($value)) {
            $_GET[$key] = sanitize_input($value);
        }
    }
}

// Check for timeout message
if (isset($_SESSION['timeout_message'])) {
    flash_message('warning', $_SESSION['timeout_message']);
    unset($_SESSION['timeout_message']);
}

// Log page access
if (LOGGING_ENABLED && is_logged_in()) {
    $user_id = $_SESSION['user_id'] ?? 'guest';
    $page = $_SERVER['PHP_SELF'] ?? 'unknown';
    $query = $_SERVER['QUERY_STRING'] ?? '';
    $full_url = $page . ($query ? "?$query" : '');
    
    system_log("User $user_id accessed $full_url", 'INFO', 'access');
}
?>