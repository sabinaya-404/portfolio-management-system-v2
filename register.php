<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mail.php';

if (is_logged_in()) {
   header('Location: dashboard.php');
    exit;
}

$errors = [];
$emailExistsError = 'User with this email already exists, Forget Password?';
$fullName = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    }

    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($fullName === '') {
        $errors[] = 'Full name is required.';
    } elseif (!preg_match('/^[\p{L}\s\'-]+$/u', $fullName)) { // REG-001 Fix: Unicode-aware regex for international names
        $errors[] = 'Full name can only contain letters, spaces, hyphens, and apostrophes.';
    } elseif (strlen($fullName) > 100) {
        $errors[] = 'Full name cannot exceed 100 characters.';
    }

    if ($email === '') {
        $errors[] = 'Email is required.';
    } else {
        $email = strtolower($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            $errors[] = 'A valid email address is required.';
        }
    }

    if ($password === '') {
        $errors[] = 'Password is required.';
    } else {
        $passwordLength = strlen($password);
        if (
            $passwordLength < 8 ||
            $passwordLength > 16 ||
            !preg_match('/[A-Z]/', $password) ||
            !preg_match('/[a-z]/', $password) ||
            !preg_match('/[0-9]/', $password) ||
            !preg_match('/[^A-Za-z0-9]/', $password)
        ) {
            $errors[] = 'Password must be 8-16 characters long and include at least one uppercase letter, one lowercase letter, one number, and one special character.';
        }
    }

    if (empty($errors)) {
        $pdo = require __DIR__ . '/config/database.php';

        $stmt = $pdo->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $errors[] = $emailExistsError;
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            try {
                $insertStmt = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, "user")');
                $insertStmt->execute([$fullName, $email, $passwordHash]);

                $userId = $pdo->lastInsertId();

                // Generate verification code
                $code = random_int(100000, 999999);
                $codeHash = hash('sha256', (string)$code);

                // Invalidate any existing verification codes for this user
                $deleteStmt = $pdo->prepare('DELETE FROM email_verifications WHERE user_id = ?');
                $deleteStmt->execute([$userId]);

                // Store the verification code hash
                $insertVerifStmt = $pdo->prepare('INSERT INTO email_verifications (user_id, code_hash, failed_attempts, expires_at) VALUES (?, ?, 0, DATE_ADD(NOW(), INTERVAL 10 MINUTE))');
                $insertVerifStmt->execute([$userId, $codeHash]);

                $verifId = $pdo->lastInsertId();

                // Send verification email
                error_log('MAIL_FROM_ADDRESS diagnostics: _ENV set=' .
                    (isset($_ENV['MAIL_FROM_ADDRESS']) ? 'true' : 'false') .
                    ', _ENV value=' .
                    (isset($_ENV['MAIL_FROM_ADDRESS']) ? $_ENV['MAIL_FROM_ADDRESS'] : 'null') .
                    ', _SERVER set=' .
                    (isset($_SERVER['MAIL_FROM_ADDRESS']) ? 'true' : 'false'));
                $emailSent = sendVerificationEmail($email, $code);
                if ($emailSent) {
                    header('Location: verify-email.php?id=' . $verifId);
                    exit;
                } else {
                    $errors[] = 'Failed to send verification email. Please try again.';
                }
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $errors[] = $emailExistsError;
                } else {
                    $errors[] = 'Unable to complete registration. Please try again later.';
                }
            }
        }
    }
}

$isSuccess = isset($_GET['success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Portfolio Management System</title>
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
                <span class="sidebar-kicker">New Account Registration</span>
                <h2 class="sidebar-title">Organize and document your investments.</h2>
                <p class="sidebar-desc">Create your local portfolio management account to track stock quantities, total capital invested, and multiple Demat accounts.</p>
                <ul class="sidebar-features">
                    <li><span class="feature-bullet" aria-hidden="true"></span> Centralized multi-Demat record management</li>
                    <li><span class="feature-bullet" aria-hidden="true"></span> Direct investment and quantity calculation</li>
                    <li><span class="feature-bullet" aria-hidden="true"></span> Private, secure, and password-protected</li>
                </ul>
            </div>

            <div class="auth-sidebar-footer">
                <span>PMS V2 &bull; BCA Academic Project</span>
            </div>
        </aside>

        <section class="auth-content">
            <header class="auth-header">
                <h1>Create Account</h1>
                <p>Register to manage your Demat accounts and holdings.</p>
            </header>

            <?php if ($isSuccess): ?>
                <div class="alert alert-success" role="status">
                    Registration successful! You can now <a href="login.php">log in</a> to your account.
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error" role="alert">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form action="register.php" method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        class="form-control"
                        value="<?php echo htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?>"
                        required
                        maxlength="100"
                        autocomplete="name"
                    >
                </div>

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
                        autocomplete="new-password"
                        minlength="8"
                        maxlength="16"
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
                    <small class="form-hint">Must be 8-16 characters with uppercase, lowercase, number, and special character.</small>
                </div>

                <button type="submit" class="btn btn-primary">
                    <span class="button-text">Register</span>
                    <span class="spinner" aria-hidden="true"></span>
                </button>
            </form>

            <footer class="auth-footer">
                Already have an account? <a href="login.php">Log in</a>
            </footer>
        </section>
    </main>
    <script src="assets/js/auth.js" defer></script>
</body>
</html>