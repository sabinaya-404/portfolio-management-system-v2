<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$message = 'If an account matches that email, a password reset link has been sent.';
$email = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $email = strtolower(trim($_POST['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid email address.';
        } else {
            $pdo = require __DIR__ . '/config/database.php';
            $stmt = $pdo->prepare('SELECT user_id, email FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                $token = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);
                $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$user['user_id']]);
                $resetStmt = $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))');
                $resetStmt->execute([$user['user_id'], $tokenHash]);
                $resetUrl = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/reset-password.php?token=' . urlencode($token);
                require_once __DIR__ . '/includes/mail.php';
                sendPasswordResetEmail($user['email'], $resetUrl);
            }
            $message = 'If an account matches that email, a password reset link has been sent.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Portfolio Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <main class="auth-wrapper">
        <section class="auth-content">
            <header class="auth-header">
                <h1>Forgot Password</h1>
                <p>Request a secure password reset link.</p>
            </header>
            <?php if ($error): ?>
                <div class="alert alert-error" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php else: ?>
                <div class="alert alert-info" role="status"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <form action="forgot-password.php" method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" required maxlength="255" autocomplete="email">
                </div>
                <button type="submit" class="btn btn-primary">Send Reset Link</button>
            </form>
            <footer class="auth-footer"><a href="login.php">Back to login</a></footer>
        </section>
    </main>
</body>
</html>
