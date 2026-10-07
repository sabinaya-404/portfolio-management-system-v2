<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
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
    } elseif (!preg_match('/^[a-zA-Z\s]+$/', $fullName)) {
        $errors[] = 'Full name can only contain letters and spaces.';
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
            $errors[] = 'An account with this email address already exists.';
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            try {
                $insertStmt = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, "user")');
                $insertStmt->execute([$fullName, $email, $passwordHash]);

                header('Location: register.php?success=1');
                exit;
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $errors[] = 'An account with this email address already exists.';
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
    <main class="auth-container">
        <div class="auth-card">
            <header class="auth-header">
                <h1>Create Account</h1>
                <p>Register to manage your portfolio</p>
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

                <div class="form-group">
                    <label for="password">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        required
                        minlength="8"
                        maxlength="16"
                        autocomplete="new-password"
                    >
                    <small class="form-hint">Must be 8-16 characters with uppercase, lowercase, number, and special character.</small>
                </div>

                <button type="submit" class="btn btn-primary">Register</button>
            </form>

            <footer class="auth-footer">
                Already have an account? <a href="login.php">Log in</a>
            </footer>
        </div>
    </main>
</body>
</html>
