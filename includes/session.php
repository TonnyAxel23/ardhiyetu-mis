<?php
// Session management
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set session timeout (2 hours)
$timeout = 7200;
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > $timeout)) {
    // Don't call logout_user() here to avoid circular dependencies
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['flash']['info'] = 'Your session has expired. Please login again.';
    header('Location: login.php');
    exit();
}
$_SESSION['LAST_ACTIVITY'] = time();

// Regenerate session ID every 30 minutes for security
if (!isset($_SESSION['CREATED'])) {
    $_SESSION['CREATED'] = time();
} elseif (time() - $_SESSION['CREATED'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['CREATED'] = time();
}

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>