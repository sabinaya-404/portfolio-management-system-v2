<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mail.php';
$userId = null;
$verifId = null;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        // Determine user_id: either from session (logged in) or from GET id (if provided)
        if (is_logged_in()) {
            $userId = get_current_user_id();
        } else {
            $idGet = $_GET['id'] ?? '';
            if ($idGet && preg_match('/^\d+$/', $idGet)) {
                // Fetch the verification row to get user_id and check expiration
                $pdo = require __DIR__ . '/config/database.php';
                $stmt = $pdo->prepare('SELECT user_id, failed_attempts, expires_at FROM email_verifications WHERE id = ?');
                $stmt->execute([$idGet]);
                $row = $stmt->fetch();
                if ($row) {
                    $userId = $row['user_id'];
                    $verifId = $idGet;
                    // Check if expired
                    $now = new DateTime();
                    $expires = new DateTime($row['expires_at']);
                    if ($now > $expires) {
                        $error = 'Verification link has expired. Please register again.';
                        $userId = null;
                    }
                } else {
                    $error = 'Invalid verification link.';
                }
            } else {
                $error = 'Invalid request.';
            }
        }

        if (!$error && $userId !== null) {
            $pdo = require __DIR__ . '/config/database.php';
            // Check rate limiting: at least 2 minutes since last sent code
            // We'll look for the most recent verification row for this user
            $stmt = $pdo->prepare('SELECT created_at FROM email_verifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 1');
            $stmt->execute([$userId]);
            $last = $stmt->fetch();

            if ($last) {
                $lastSent = new DateTime($last['created_at']);
                $now = new DateTime();
                $interval = $now->getTimestamp() - $lastSent->getTimestamp();
                if ($interval < 120) { // 2 minutes in seconds
                    $wait = 120 - $interval;
                    $minutes = floor($wait / 60);
                    $seconds = $wait % 60;
                    $error = sprintf('Please wait %d minute%s %d second%s before requesting a new code.',
                        $minutes, $minutes !== 1 ? 's' : '',
                        $seconds, $seconds !== 1 ? 's' : '');
                }
            }
        }

        if (!$error && $userId !== null) {
            // Generate new code
            $code = random_int(100000, 999999);
            $codeHash = hash('sha256', (string)$code);
            if ($verifId !== null) {
                // Update the existing row
                $updateStmt = $pdo->prepare('UPDATE email_verifications SET code_hash = ?, failed_attempts = 0, expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE) WHERE id = ?');
                $updateStmt->execute([$codeHash, $verifId]);
                $newVerifId = $verifId;
            } else {
                // Delete existing verification codes for this user
                $deleteStmt = $pdo->prepare('DELETE FROM email_verifications WHERE user_id = ?');
                $deleteStmt->execute([$userId]);

                // Insert new verification code
                $insertStmt = $pdo->prepare('INSERT INTO email_verifications (user_id, code_hash, failed_attempts, expires_at) VALUES (?, ?, 0, DATE_ADD(NOW(), INTERVAL 10 MINUTE))');
                $insertStmt->execute([$userId, $codeHash]);
                $newVerifId = $pdo->lastInsertId();
            }

            // Get user email
            $emailStmt = $pdo->prepare('SELECT email FROM users WHERE user_id = ?');
            $emailStmt->execute([$userId]);
            $user = $emailStmt->fetch();
            if ($user && !empty($user['email'])) {
                if (sendVerificationEmail($user['email'], $code)) {
                    header('Location: verify-email.php?id=' . $newVerifId);
                    exit;
                } else {
                    $error = 'Failed to send verification email. Please try again.';
                }
            } else {
                $error = 'Unable to retrieve user email. Please contact support.';
            }
        }
    }
} else {
    if (is_logged_in()) {
        $userId = get_current_user_id();
        if ($userId !== null) {
            $pdo = require __DIR__ . '/config/database.php';
            $stmt = $pdo->prepare('SELECT created_at FROM email_verifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 1');
            $stmt->execute([$userId]);
            $last = $stmt->fetch();

            if ($last) {
                $lastSent = new DateTime($last['created_at']);
                $now = new DateTime();
                $interval = $now->getTimestamp() - $lastSent->getTimestamp();
                if ($interval < 120) {
                    $wait = 120 - $interval;
                    $minutes = floor($wait / 60);
                    $seconds = $wait % 60;
                    $error = sprintf('Please wait %d minute%s %d second%s before requesting a new code.',
                        $minutes, $minutes !== 1 ? 's' : '',
                        $seconds, $seconds !== 1 ? 's' : '');
                }
            }
        }
    } else {
        $idGet = $_GET['id'] ?? '';
        if ($idGet && preg_match('/^\d+$/', $idGet)) {
            $pdo = require __DIR__ . '/config/database.php';
            $stmt = $pdo->prepare('SELECT user_id, failed_attempts, created_at, expires_at FROM email_verifications WHERE id = ?');
            $stmt->execute([$idGet]);
            $row = $stmt->fetch();
            if ($row) {
                $userId = $row['user_id'];
                // Check expiration
                $now = new DateTime();
                $expires = new DateTime($row['expires_at']);
                if ($now > $expires) {
                    $error = 'Verification link has expired. Please register again.';
                } else {
                    // Check rate limiting based on this row's created_at
                    $lastSent = new DateTime($row['created_at']);
                    $now = new DateTime();
                    $interval = $now->getTimestamp() - $lastSent->getTimestamp();
                    if ($interval < 120) {
                        $wait = 120 - $interval;
                        $minutes = floor($wait / 60);
                        $seconds = $wait % 60;
                        $error = sprintf('Please wait %d minute%s %d second%s before requesting a new code.',
                            $minutes, $minutes !== 1 ? 's' : '',
                            $seconds, $seconds !== 1 ? 's' : '');
                    }
                }
            } else {
                $error = 'Invalid verification link.';
            }
        } else {
            $error = 'Invalid request.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resend Verification - Portfolio Management System</title>
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
                <span class="sidebar-kicker">Resend Verification</span>
                <h2 class="sidebar-title">Resend verification code</h2>
                <p class="sidebar-desc">Request a new verification code to be sent to your email address.</p>
                <ul class="sidebar-features">
                    <li><span class="feature-bullet" aria-hidden="true"></span> Rate limited to 1 resend every 2 minutes</li>
                    <li><span class="feature-bullet" aria-hidden="true"></span> New code expires in 10 minutes</li>
                </ul>
            </div>

            <div class="auth-sidebar-footer">
                <span>PMS V2 &bull; BCA Academic Project</span>
            </div>
        </aside>

        <section class="auth-content">
            <header class="auth-header">
                <h1>Resend Verification Code</h1>
                <p>Request a new verification code to be sent to your email.</p>
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

            <?php if (!$error && !$success): ?>
                <p>If you didn't receive the verification code, you can request a new one.</p>
                <p>Note: For security reasons, you can only request a new code every 2 minutes.</p>
            <?php endif; ?>

            <form action="resend-verification.php" method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <?php if (is_logged_in() || ($_GET['id'] ?? '')): ?>
                    <?php if (!$error): ?>
                        <button type="submit" class="btn btn-primary">
                            <span class="button-text">Resend Code</span>
                            <span class="spinner" aria-hidden="true"></span>
                        </button>
                    <?php endif; ?>
                <?php endif; ?>
            </form>

            <div class="form-hint">
                <a href="verify-email.php<?php echo isset($_GET['id']) ? '?id=' . htmlspecialchars($_GET['id'], ENT_QUOTES, 'UTF-8') : ''; ?>">Back to verification</a>
            </div>
        </section>
    </main>
    <script src="assets/js/auth.js" defer></script>
</body>
</html>