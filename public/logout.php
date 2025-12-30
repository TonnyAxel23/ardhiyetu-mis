<?php
// public/logout.php
require_once '../includes/init.php';

// Check if user is logged in
if (is_logged_in()) {
    // Get user info before logout for logging
    $user_id = $_SESSION['user_id'] ?? null;
    $user_name = $_SESSION['name'] ?? 'Unknown';
    
    // Perform logout
    logout_user();
    
    // Set success message in new session
    session_start();
    $_SESSION['flash']['success'] = "Goodbye, $user_name! You have been successfully logged out.";
    
    // Redirect to login page
    header('Location: ' . BASE_URL . '/login.php');
    exit();
} else {
    // User is already logged out, redirect to login
    header('Location: ' . BASE_URL . '/login.php');
    exit();
}
?>