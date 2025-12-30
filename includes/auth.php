<?php
// Authentication functions

function require_login() {
    if (!is_logged_in()) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        flash_message('error', 'Please login to access this page.');
        redirect(BASE_URL . '/login.php');
    }
}

function require_admin() {
    require_login();
    if (!is_admin()) {
        flash_message('error', 'Access denied. Admin privileges required.');
        redirect(BASE_URL . '/dashboard.php');
    }
}

function require_officer() {
    require_login();
    if (!is_officer()) {
        flash_message('error', 'Access denied. Officer privileges required.');
        redirect(BASE_URL . '/dashboard.php');
    }
}

function login_user($user_id, $email, $role, $name) {
    $_SESSION['user_id'] = $user_id;
    $_SESSION['email'] = $email;
    $_SESSION['role'] = $role;
    $_SESSION['name'] = $name;
    $_SESSION['login_time'] = time();
    
    // Log login activity
    log_activity($user_id, 'login', "User logged in");
}

function logout_user() {
    // Get user ID before destroying session
    $user_id = $_SESSION['user_id'] ?? null;
    
    if ($user_id) {
        log_activity($user_id, 'logout', "User logged out");
    }
    
    // Clear all session variables
    $_SESSION = array();
    
    // Destroy the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
    
    // Clear remember me cookie if exists
    if (isset($_COOKIE['ardhiyetu_remember'])) {
        setcookie('ardhiyetu_remember', '', time() - 3600, '/', '', true, true);
    }
}

// CSRF Token verification function (needed for register.php)
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>