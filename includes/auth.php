<?php
/**
 * Authentication and authorization helper functions
 *
 * This file provides reusable authentication and authorization functions
 * that can be used throughout the application.
 */

require_once __DIR__ . "/session.php";
require_once __DIR__ . "/../config/database.php";

/**
 * Check if the current user is logged in
 *
 * @return bool True if user is logged in, false otherwise
 */
function is_logged_in(): bool {
    return isset($_SESSION["user_id"]);
}

/**
 * Get the current user's ID
 *
 * @return int|null User ID if logged in, null otherwise
 */
function get_current_user_id(): ?int {
    return isset($_SESSION["user_id"]) ? (int)$_SESSION["user_id"] : null;
}

/**
 * Get the current user's name
 *
 * @return string|null User name if logged in, null otherwise
 */
function get_current_user_name(): ?string {
    return isset($_SESSION["user_name"]) ? $_SESSION["user_name"] : null;
}

/**
 * Get the current user's role
 *
 * @return string|null User role if logged in, null otherwise
 */
function get_current_user_role(): ?string {
    return isset($_SESSION["role"]) ? $_SESSION["role"] : null;
}

/**
 * Check if the current user has a specific role
 *
 * @param string $role The role to check for
 * @return bool True if user has the specified role, false otherwise
 */
function has_role(string $role): bool {
    return isset($_SESSION["role"]) && $_SESSION["role"] === $role;
}

/**
 * Check if the current user is an admin
 *
 * @return bool True if user is an admin, false otherwise
 */
function is_admin(): bool {
    return has_role("admin");
}

/**
 * Redirect to login page if user is not logged in
 *
 * This function should be called at the top of any protected page
 * to ensure the user is authenticated before proceeding.
 */
function require_login(): void {
    if (!is_logged_in()) {
        header("Location: login.php");
        exit;
    }
}

/**
 * Redirect to login page if user does not have the required role
 *
 * @param string $required_role The role required to access the page
 */
function require_role(string $required_role): void {
    require_login(); // Must be logged in first

    if (!has_role($required_role)) {
        // Redirect to an appropriate page based on role
        // For now, redirect to dashboard - could be enhanced to show access denied
        header("Location: dashboard.php");
        exit;
    }
}

/**
 * Log out the current user
 *
 * This function destroys the session and redirects to the login page.
 */
function logout(): void {
    // Unset all session variables
    $_SESSION = [];

    // Delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    // Destroy the session
    session_destroy();

    // Redirect to login page
    header("Location: login.php");
    exit;
}

/**
 * Validate password strength according to requirements
 *
 * @param string $password The password to validate
 * @return bool True if password meets requirements, false otherwise
 */
function validate_password_strength(string $password): bool {
    // Check length
    if (strlen($password) < 8 || strlen($password) > 16) {
        return false;
    }

    // Check for at least one uppercase letter
    if (!preg_match('/[A-Z]/', $password)) {
        return false;
    }

    // Check for at least one lowercase letter
    if (!preg_match('/[a-z]/', $password)) {
        return false;
    }

    // Check for at least one number
    if (!preg_match('/[0-9]/', $password)) {
        return false;
    }

    // Check for at least one special character
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        return false;
    }

    return true;
}

/**
 * Hash a password for storage
 *
 * @param string $password The plain text password to hash
 * @return string The hashed password
 */
function hash_password(string $password): string {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verify a password against a hash
 *
 * @param string $password The plain text password to verify
 * @param string $hash The hashed password to verify against
 * @return bool True if password matches hash, false otherwise
 */
function verify_password(string $password, string $hash): bool {
    return password_verify($password, $hash);
}