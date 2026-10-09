<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation at the top of POST block
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
                    // AUTH-001 Fix: Check account status before password verification to prevent timing attacks
                    if ($user['account_status'] !== 'active') {
                        $error = 'Your account has been disabled. Please contact an administrator.';
                    } else {
                        if (password_verify($password, $user['password_hash'])) {
                            if ((int)$user['failed_login_attempts'] > 0 || $user['locked_until'] !== null) {
                                $resetStmt = $pdo->prepare('UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE user_id = ?');
                                $resetStmt->execute([$user['user_id']]);
                            }

                            // Sess-001 Fix: Regenerate session ID before setting session variables to prevent fixation
                            session_regenerate_id(true);
                            $_SESSION['user_id'] = (int)$user['user_id'];
                            $_SESSION['user_name'] = $user['full_name'];
                            $_SESSION['role'] = $user['role'];

                            header('Location: dashboard.php');
                            exit;
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
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - Portfolio Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <main class="auth-wrapper">
        <aside class="auth-sidebar">
            <div class="auth-sidebar-brand">
                <div class="brand-badge" aria-hidden="true">PMS</div>
                <div>
                    <span class="brand-name">Portfolio Management System</span>
                    <span class="brand-tag">Record Management V2</span>
                </div>
            </div>

            <div class="auth-sidebar-body">
                <!-- Subtle financial illustration -->
                <div class="sidebar-illustration" aria-hidden="true">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                    </svg>
                </div>
                <span class="sidebar-kicker">Personal Investment Ledger</span>
                <h2 class="sidebar-title">Track your holdings across Demat accounts.</h2>
                <p class="sidebar-desc">A focused, distraction-free environment to maintain your stock portfolio records and total invested amounts.</p>
                <ul class="sidebar-features">
                    <li><span class="feature-bullet" aria-hidden="true"></span> Manage up to 10 Demat accounts</li>
                    <li><span class="feature-bullet" aria-hidden="true"></span> Accurate holding and cost recording</li>
                    <li><span class="feature-bullet" aria-hidden="true"></span> Up-to-date company directory and market news</li>
                </ul>
            </div>

            <div class="auth-sidebar-footer">
                <span>PMS V2 &bull; BCA Academic Project</span>
            </div>
        </aside>

        <section class="auth-content">
            <header class="auth-header">
                <h1>Sign In</h1>
                <p>Enter your credentials to access your Demat portfolio.</p>
            </header>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error" role="alert">
                    <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-control"
                        value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>"
                        required
                        maxlength="255"
                        autocomplete="email"
                    >
                </div>

                <div class="form-group password-toggle">
                    <label for="password">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        required
                        autocomplete="current-password"
                    >
                    <button type="button" class="password-toggle-button" aria-label="Show password">
                        <svg class="toggle-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <g class="eye-open">
                                <ellipse cx="12" cy="12" rx="10" ry="6"/>
                                <circle cx="12" cy="12" r="3"/>
                            </g>
                            <g class="eye-closed">
                                <ellipse cx="12" cy="12" rx="10" ry="6"/>
                                <circle cx="12" cy="12" r="3"/>
                                <line x1="5" y1="5" x2="19" y2="19"/>
                            </g>
                        </svg>
                    </button>
                </div>

                <button type="submit" class="btn btn-primary">
                    <span class="button-text">Sign In</span>
                    <span class="spinner" aria-hidden="true"></span>
                </button>
            </form>

            <footer class="auth-footer">
                Don't have an account? <a href="register.php">Register</a>
            </footer>
        </section>
    </main>
    <script src="assets/js/auth.js" defer></script>
</body>
</html>