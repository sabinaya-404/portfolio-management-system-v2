<?php
require_once __DIR__ . '/includes/auth.php';

$id = $_GET['id'] ?? '';
if (!preg_match('/^\d+$/', $id)) {
}

$pdo = require __DIR__ . '/config/database.php';

$stmt = $pdo->prepare('SELECT ev.id, ev.user_id, ev.code_hash, ev.failed_attempts, ev.expires_at, u.email, u.full_name, u.role FROM email_verifications ev JOIN users u ON ev.user_id = u.user_id WHERE ev.id = ?');
$stmt->execute([$id]);
$verif = $stmt->fetch();

$error = '';
$success = '';

if (!$verif) {
    $error = 'Invalid or expired verification link.';
} else {
    $now = new DateTime();
    $expires = new DateTime($verif['expires_at']);
    if ($now > $expires) {
        $error = 'Verification link has expired. Please request a new code.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $code = trim($_POST['code'] ?? '');
        if ($code === '') {
            $error = 'Please enter the verification code.';
        } elseif (!preg_match('/^\d{6}$/', $code)) {
            $error = 'Verification code must be a 6-digit number.';
        } else {
            $submittedHash = hash('sha256', $code);
            if (hash_equals($verif['code_hash'], $submittedHash)) {
                // Code is valid
                // Update user's email_verified to TRUE
                $updateStmt = $pdo->prepare('UPDATE users SET email_verified = TRUE WHERE user_id = ?');
                $updateStmt->execute([$verif['user_id']]);

                // Delete the verification row
                $deleteStmt = $pdo->prepare('DELETE FROM email_verifications WHERE id = ?');
                $deleteStmt->execute([$id]);

                // Log the user in
                $_SESSION['user_id'] = (int)$verif['user_id'];
                $_SESSION['user_name'] = $verif['full_name'];
                $_SESSION['role'] = $verif['role'];

                header('Location: dashboard.php');
                exit;
            } else {
                // Increment failed attempts
                $newFailedAttempts = (int)$verif['failed_attempts'] + 1;

                // Update failed attempts counter
                $updateAttemptsStmt = $pdo->prepare('UPDATE email_verifications SET failed_attempts = ? WHERE id = ?');
                $updateAttemptsStmt->execute([$newFailedAttempts, $id]);

                // Check if max attempts reached
                if ($newFailedAttempts >= 5) {
                    // Delete the verification record after 5 failed attempts
                    $deleteStmt = $pdo->prepare('DELETE FROM email_verifications WHERE id = ?');
                    $deleteStmt->execute([$id]);
                    $error = 'Maximum verification attempts exceeded. Please request a new verification code.';
                } else {
                    $error = 'Invalid verification code. Please try again. Attempts remaining: ' . (5 - $newFailedAttempts);
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
    <title>Verify Email - Portfolio Management System</title>
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
                <span class="sidebar-kicker">Email Verification</span>
                <h2 class="sidebar-title">Verify your email address</h2>
                <p class="sidebar-desc">Enter the 6-digit code sent to your email to verify your account.</p>
                <ul class="sidebar-features">
                    <li><span class="feature-bullet" aria-hidden="true"></span> Secure verification</li>
                    <li><span class="feature-bullet" aria-hidden="true"></span> Code expires in 10 minutes</li>
                    <li><span class="feature-bullet" aria-hidden="true"></span> Resend available after 2 minutes</li>
                </ul>
            </div>

            <div class="auth-sidebar-footer">
                <span>PMS V2 &bull; BCA Academic Project</span>
            </div>
        </aside>

        <section class="auth-content">
            <header class="auth-header">
                <h1>Verify Email Address</h1>
                <p>Please check your inbox for the verification code.</p>
            </header>

            <?php if ($error): ?>
                <div class="alert alert-error" role="alert">
                    <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success" role="status">
                    <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <form action="verify-email.php?id=<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>" method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

                <div class="form-group">
                    <label for="code">Verification Code</label>
                    <input
                        type="text"
                        id="code"
                        name="code"
                        class="form-control"
                        required
                        maxlength="6"
                        placeholder="XXXXXX"
                    >
                </div>

                <button type="submit" class="btn btn-primary">
                    <span class="button-text">Verify Code</span>
                    <span class="spinner" aria-hidden="true"></span>
                </button>
            </form>

            <div class="form-hint">
                Didn't receive the code? You can request a new one after 2 minutes.
                <br>
                Or <a href="login.php">continue to login without verifying</a> (you can verify later from your account settings).
            </div>
        </section>
    </main>
    <script src="assets/js/auth.js" defer></script>
</body>
</html>