<?php
require_once __DIR__ . '/includes/auth.php';

if (!is_logged_in()) {
    header('Location: ./login.php');
    exit;
}

$userId = get_current_user_id();
$userName = get_current_user_name();
$pdo = require __DIR__ . '/config/database.php';
$dematStmt = $pdo->prepare('SELECT demat_id, account_nickname FROM demat_accounts WHERE user_id = ? ORDER BY account_nickname');
$dematStmt->execute([$userId]);
$dematAccounts = $dematStmt->fetchAll();
$totalStmt = $pdo->prepare('SELECT COALESCE(SUM(h.total_investment), 0) FROM holdings h JOIN demat_accounts d ON d.demat_id = h.demat_id WHERE d.user_id = ?');
$totalStmt->execute([$userId]);
$totalInvested = $totalStmt->fetchColumn();

// Check email verification status
$emailVerified = false;
if ($userId !== null) {
    $stmt = $pdo->prepare('SELECT email_verified FROM users WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if ($user && isset($user['email_verified'])) {
        $emailVerified = (bool)$user['email_verified'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Portfolio Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header>
        <h1>Dashboard</h1>
        <p>Welcome, <?php echo htmlspecialchars($userName ?? 'User', ENT_QUOTES, 'UTF-8'); ?>!</p>
        <nav>
            <a href="my_demat.php">Demat Accounts</a>
            <a href="account.php">Account</a>
            <a href="logout.php">Logout</a>
        </nav>
    </header>
    <main>
        <p>Welcome to your Portfolio Management System dashboard.</p>
        <p>Total invested: <?php echo htmlspecialchars(number_format((float)$totalInvested, 2), ENT_QUOTES, 'UTF-8'); ?></p>
        <?php if (!$dematAccounts): ?>
            <p>No Demat accounts yet. <a href="add_demat.php">Add your first Demat account</a>.</p>
        <?php else: ?>
            <h2>Your Demat accounts</h2>
            <ul>
                <?php foreach ($dematAccounts as $demat): ?>
                    <li><a href="holdings.php?demat_id=<?php echo (int)$demat['demat_id']; ?>"><?php echo htmlspecialchars($demat['account_nickname'], ENT_QUOTES, 'UTF-8'); ?></a></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if (!$emailVerified): ?>
            <div class="alert alert-info" role="status">
                <p>Please verify your email address to access all features. <a href="resend-verification.php">Resend verification code</a> to receive a new code, or use the verification link from your email.</p>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>