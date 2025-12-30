<?php
// Admin access check
session_start();

function require_admin() {
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        header('Location: ../public/login.php');
        exit();
    }
    
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        $_SESSION['flash']['error'] = 'Access denied. Admin privileges required.';
        header('Location: ../public/dashboard.php');
        exit();
    }
    
    // Check session timeout (30 minutes)
    if (isset($_SESSION['login_time'])) {
        $session_lifetime = 1800; // 30 minutes in seconds
        if (time() - $_SESSION['login_time'] > $session_lifetime) {
            session_unset();
            session_destroy();
            session_start();
            $_SESSION['flash']['error'] = 'Session expired. Please login again.';
            header('Location: ../public/login.php');
            exit();
        }
        
        // Update login time for active users
        $_SESSION['login_time'] = time();
    }
}
