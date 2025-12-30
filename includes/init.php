<?php
// Initialize session and database connection
session_start();

// Define BASE_URL if not defined
if (!defined('BASE_URL')) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['SCRIPT_NAME']);
    // Clean up the URL before defining
    $base_url = rtrim($protocol . $host . $path, '/\\');
    define('BASE_URL', $base_url);
}

// Include configuration and functions
require_once 'config.php';
require_once 'functions.php';
require_once 'auth.php';

// Get current page
$current_page = basename($_SERVER['PHP_SELF']);

// Get current directory path
$current_dir = dirname($_SERVER['PHP_SELF']);
$current_dir = str_replace('\\', '/', $current_dir);

// List of pages that don't require authentication
$public_pages = [
    'login.php',
    'register.php', 
    'forgot-password.php',
    'reset-password.php',
    'index.php',
    'verify-email.php'
];

// Get relative path to check if we're in auth folder
$is_auth_page = strpos($current_dir, '/auth') !== false || strpos($current_dir, '\\auth') !== false;

// Only check authentication for non-public pages
if (!in_array($current_page, $public_pages) && !$is_auth_page) {
    if (!is_logged_in()) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        flash_message('error', 'Please login to access this page.');
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit();
    }
}

// Set default timezone
date_default_timezone_set('Africa/Nairobi');

// Handle flash messages if any
if (isset($_SESSION['flash'])) {
    // Messages will be displayed by display_flash_message()
}
?>