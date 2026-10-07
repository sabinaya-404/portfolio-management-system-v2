<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {
            $error = 'Email and password are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email or password.';
        } else {
            $normalizedEmail = strtolower($email);
            $pdo = require __DIR__ . '/config/database.php';

            $stmt = $pdo->prepare('SELECT user_id, full_name, email, password_hash, role, account_status, failed_login_attempts, locked_until FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$normalizedEmail]);
            $user = $stmt->fetch();

            if (!$user) {
                $error = 'Invalid email or password.';
            } else {
                $now = time();
                $isLocked = !empty($user['locked_until']) && strtotime($user['locked_until']) > $now;

                if ($isLocked) {
                    $error = 'Your account is temporarily locked due to too many failed login attempts. Please try again later.';
                } else {
                    if (password_verify($password, $user['password_hash'])) {
                        if ($user['account_status'] !== 'active') {
                            $error = 'Your account has been disabled. Please contact an administrator.';
                        } else {
                            if ((int)$user['failed_login_attempts'] > 0 || $user['locked_until'] !== null) {
                                $resetStmt = $pdo->prepare('UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE user_id = ?');
                                $resetStmt->execute([$user['user_id']]);
                            }

                            session_regenerate_id(true);
                            $_SESSION['user_id'] = (int)$user['user_id'];
                            $_SESSION['user_name'] = $user['full_name'];
                            $_SESSION['role'] = $user['role'];

                            header('Location: dashboard.php');
                            exit;
                        }
                    } else {
                        $hadExpiredLockout = !empty($user['locked_until']) && strtotime($user['locked_until']) <= $now;
                        $attempts = $hadExpiredLockout ? 1 : (int)$user['failed_login_attempts'] + 1;

                        if ($attempts >= 5) {
                            $lockedUntil = date('Y-m-d H:i:s', $now + 20 * 60);
                            $lockStmt = $pdo->prepare('UPDATE users SET failed_login_attempts = ?, locked_until = ? WHERE user_id = ?');
                            $lockStmt->execute([$attempts, $lockedUntil, $user['user_id']]);
                            $error = 'Your account is temporarily locked due to too many failed login attempts. Please try again later.';
                        } else {
                            $updateStmt = $pdo->prepare('UPDATE users SET failed_login_attempts = ?, locked_until = NULL WHERE user_id = ?');
                            $updateStmt->execute([$attempts, $user['user_id']]);
                            $error = 'Invalid email or password.';
                        }
                    }
                }
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
    <title>Sign In - Portfolio Management System</title>
</head>
<body>
    <main>
        <div>
            <header>
                <h1>Sign In</h1>
                <p>Log in to access your portfolio</p>
            </header>

            <?php if (!empty($error)): ?>
                <div role="alert">
                    <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

                <div>
                    <label for="email">Email Address</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>"
                        required
                        maxlength="255"
                        autocomplete="email"
                    >
                </div>

                <div>
                    <label for="password">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        required
                        autocomplete="current-password"
                    >
                </div>

                <button type="submit">Sign In</button>
            </form>

            <footer>
                Don't have an account? <a href="register.php">Register</a>
            </footer>
        </div>
    </main>
</body>
</html>
