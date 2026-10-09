<?php
require_once __DIR__ . "/session.php";

function is_logged_in(): bool {
    if (!isset($_SESSION["user_id"])) {
        return false;
    }

    // Verify account hasn't been disabled since login
    $pdo = require __DIR__ . "/../config/database.php";
    $stmt = $pdo->prepare("SELECT account_status FROM users WHERE user_id = ? LIMIT 1");
    $stmt->execute([$_SESSION["user_id"]]);
    $user = $stmt->fetch();

    return $user && $user['account_status'] === 'active';
}

function get_current_user_id(): ?int {
    return isset($_SESSION["user_id"]) ? (int)$_SESSION["user_id"] : null;
}

function get_current_user_name(): ?string {
    return isset($_SESSION["user_name"]) ? $_SESSION["user_name"] : null;
}

function get_current_user_role(): ?string {
    return isset($_SESSION["role"]) ? $_SESSION["role"] : null;
}

function has_role(string $role): bool {
    return isset($_SESSION["role"]) && $_SESSION["role"] === $role;
}

function is_admin(): bool {
    return has_role("admin");
}

function require_login(): void {
    if (!is_logged_in()) {
        header("Location: login.php");
        exit;
    }
}

function require_role(string $required_role): void {
    require_login();

    if (!has_role($required_role)) {
        header("Location: dashboard.php");
        exit;
    }
}

function logout(): void {
    $_SESSION = [];
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
    session_destroy();
    header("Location: login.php");
    exit;
}