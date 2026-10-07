<?php
// Check if session needs to be started
if (session_status() === PHP_SESSION_NONE) {
    // Set secure INI values FIRST (must be before session_start())
    ini_set("session.use_strict_mode", "1");
    ini_set("session.use_only_cookies", "1");
    ini_set("session.use_trans_sid", "0");
    ini_set("session.gc_maxlifetime", "1800");

    $is_https = !empty($_SERVER['HTTPS']) &&
                strtolower($_SERVER['HTTPS']) !== 'off';

    session_set_cookie_params([
        'lifetime' => 1800,
        'path' => '/',
        'domain' => '',
        'secure' => $is_https,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

// Ensure CSRF token exists in session
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Validates CSRF token using secure comparison
 * @param string|null $token Token to validate (from form submission)
 * @return bool True if valid, false if invalid/missing
 */
function validate_csrf_token(?string $token): bool {
    if (!$token || !isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}