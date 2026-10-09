<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$error = '';
$success = '';
$reset = null;

if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    $pdo = require __DIR__ . '/config/database.php';
    $stmt = $pdo->prepare('SELECT id, user_id, expires_at, used_at FROM password_resets WHERE token_hash = ? LIMIT 1');
    $stmt->execute([hash('sha256', $token)]);
    $reset = $stmt->fetch();
    if (!$reset || $reset['used_at'] !== null || strtotime($reset['expires_at']) <= time()) {
        $reset = null;
        $error = 'This password reset link is invalid or expired.';
    }
} else {
    $error = 'This password reset link is invalid or expired.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $reset && !$error) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['password_confirmation'] ?? '';
        if (strlen($password) < 8 || strlen($password) > 16 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
            $error = 'Password must be 8-16 characters long and include uppercase, lowercase, number, and special character.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $pdo->beginTransaction();
            try {
                $passwordStmt = $pdo->prepare('UPDATE users SET password_hash = ?, failed_login_attempts = 0, locked_until = NULL WHERE user_id = ?');
                $passwordStmt->execute([password_hash($password, PASSWORD_DEFAULT), $reset['user_id']]);
                $usedStmt = $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?');
                $usedStmt->execute([$reset['id']]);
                $pdo->commit();
                $success = 'Your password has been reset. You can now log in.';
                $reset = null;
            } catch (Throwable $exception) {
                $pdo->rollBack();
                $error = 'Unable to reset the password. Please try again later.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Portfolio Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <main class="auth-wrapper">
        <section class="auth-content">
            <header class="auth-header">
                <h1>Reset Password</h1>
                <p>Choose a new password for your account.</p>
            </header>
            <?php if ($error): ?><div class="alert alert-error" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success" role="status"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
            <?php if ($reset): ?>
                <form action="reset-password.php" method="POST" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="form-group"><label for="password">New Password</label><input type="password" id="password" name="password" class="form-control" required minlength="8" maxlength="16" autocomplete="new-password"></div>
                    <div class="form-group"><label for="password_confirmation">Confirm Password</label><input type="password" id="password_confirmation" name="password_confirmation" class="form-control" required minlength="8" maxlength="16" autocomplete="new-password"></div>
                    <button type="submit" class="btn btn-primary">Reset Password</button>
                </form>
            <?php endif; ?>
            <footer class="auth-footer"><a href="login.php">Back to login</a></footer>
        </section>
    </main>
</body>
</html>
