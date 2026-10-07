<?php
require_once __DIR__ . '/includes/auth.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

$userName = get_current_user_name();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Portfolio Management System</title>
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
    </main>
</body>
</html>
