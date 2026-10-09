<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$pdo = require __DIR__ . '/config/database.php';
$userId = get_current_user_id();
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'password') {
            $current = $_POST['current_password'] ?? '';
            $password = $_POST['password'] ?? '';
            $confirm = $_POST['password_confirmation'] ?? '';
            $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE user_id = ?');
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            if (!$user || !password_verify($current, $user['password_hash'])) {
                $errors[] = 'Current password is incorrect.';
            } elseif (strlen($password) < 8 || strlen($password) > 16 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
                $errors[] = 'Password must be 8-16 characters long and include uppercase, lowercase, number, and special character.';
            } elseif ($password !== $confirm) {
                $errors[] = 'Passwords do not match.';
            } else {
                $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?');
                $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
                $success = 'Password changed successfully.';
            }
        } elseif ($action === 'email') {
            $newEmail = strtolower(trim($_POST['new_email'] ?? ''));
            if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Enter a valid email address.';
            } else {
                $stmt = $pdo->prepare('SELECT user_id FROM users WHERE email = ? AND user_id <> ? LIMIT 1');
                $stmt->execute([$newEmail, $userId]);
                if ($stmt->fetch()) {
                    $errors[] = 'That email address is already in use.';
                } else {
                    $code = (string)random_int(100000, 999999);
                    $pdo->prepare('DELETE FROM email_change_verifications WHERE user_id = ?')->execute([$userId]);
                    $stmt = $pdo->prepare('INSERT INTO email_change_verifications (user_id, new_email, code_hash, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))');
                    $stmt->execute([$userId, $newEmail, hash('sha256', $code)]);
                    require_once __DIR__ . '/includes/mail.php';
                    if (sendEmailChangeVerification($newEmail, $code)) {
                        $success = 'A verification code was sent to the new email address.';
                    } else {
                        $errors[] = 'Unable to send the verification email.';
                    }
                }
            }
        } elseif ($action === 'verify_email') {
            $code = trim($_POST['verification_code'] ?? '');
            $stmt = $pdo->prepare('SELECT id, new_email, code_hash, expires_at FROM email_change_verifications WHERE user_id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $verification = $stmt->fetch();
            if (!$verification || strtotime($verification['expires_at']) <= time() || !preg_match('/^\d{6}$/', $code) || !hash_equals($verification['code_hash'], hash('sha256', $code))) {
                $errors[] = 'The verification code is invalid or expired.';
            } else {
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare('UPDATE users SET email = ?, email_verified = TRUE WHERE user_id = ?');
                    $stmt->execute([$verification['new_email'], $userId]);
                    $pdo->prepare('DELETE FROM email_change_verifications WHERE id = ?')->execute([$verification['id']]);
                    $pdo->commit();
                    $success = 'Email address updated successfully.';
                } catch (Throwable $exception) {
                    $pdo->rollBack();
                    $errors[] = 'Unable to update the email address.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Account - Portfolio Management System</title><link rel="stylesheet" href="assets/css/style.css"></head>
<body>
<header><h1>Account</h1><nav><a href="dashboard.php">Dashboard</a> <a href="logout.php">Logout</a></nav></header>
<main>
<?php if ($errors): ?><div class="alert alert-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success" role="status"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
<h2>Change Password</h2>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="action" value="password"><label>Current password<input type="password" name="current_password" required></label><label>New password<input type="password" name="password" minlength="8" maxlength="16" required></label><label>Confirm password<input type="password" name="password_confirmation" minlength="8" maxlength="16" required></label><button type="submit">Change password</button></form>
<h2>Change Email</h2>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="action" value="email"><label>New email<input type="email" name="new_email" maxlength="255" required></label><button type="submit">Send verification code</button></form>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="action" value="verify_email"><label>Verification code<input type="text" name="verification_code" maxlength="6" required></label><button type="submit">Verify new email</button></form>
</main>
</body>
</html>
