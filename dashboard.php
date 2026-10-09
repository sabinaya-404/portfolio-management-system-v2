<?php
require_once __DIR__ . '/includes/auth.php';

if (!is_logged_in()) {
    header('Location: ./login.php');
    exit;
}

$userId = get_current_user_id();
$userName = get_current_user_name();

// Check email verification status
$emailVerified = false;
if ($userId !== null) {
    $pdo = require __DIR__ . '/config/database.php';
    $stmt = $pdo->prepare('SELECT email_verified FROM users WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if ($user && isset($user['email_verified'])) {
        $emailVerified = (bool)$user['email_verified'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Portfolio Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header>
        <h1>Dashboard</h1>
        <p>Welcome, <?php echo htmlspecialchars($userName ?? 'User', ENT_QUOTES, 'UTF-8'); ?>!</p>
        <nav>
            <a href="logout.php">Logout</a>
        </nav>
    </header>
    <main>
        <p>Welcome to your Portfolio Management System dashboard.</p>
        <?php if (!$emailVerified): ?>
            <div class="alert alert-info" role="status">
                <p>Please verify your email address to access all features. <a href="resend-verification.php">Resend verification code</a> to receive a new code, or use the verification link from your email.</p>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>