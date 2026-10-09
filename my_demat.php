<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$pdo = require __DIR__ . '/config/database.php';
$stmt = $pdo->prepare('SELECT demat_id, account_nickname, account_holder_name, dp_broker_name, dp_broker_number, boid FROM demat_accounts WHERE user_id = ? ORDER BY demat_id DESC');
$stmt->execute([get_current_user_id()]);
$dematAccounts = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Demat Accounts - Portfolio Management System</title><link rel="stylesheet" href="assets/css/style.css"></head>
<body>
<header><h1>Demat Accounts</h1><nav><a href="dashboard.php">Dashboard</a> <a href="add_demat.php">Add Demat</a> <a href="account.php">Account</a> <a href="logout.php">Logout</a></nav></header>
<main>
<?php if (!$dematAccounts): ?><p>No Demat accounts have been added.</p><?php else: ?>
<?php foreach ($dematAccounts as $demat): ?>
<article>
<h2><?php echo htmlspecialchars($demat['account_nickname'], ENT_QUOTES, 'UTF-8'); ?></h2>
<p>Account holder: <?php echo htmlspecialchars($demat['account_holder_name'], ENT_QUOTES, 'UTF-8'); ?></p>
<p>DP/Broker: <?php echo htmlspecialchars($demat['dp_broker_name'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars($demat['dp_broker_number'], ENT_QUOTES, 'UTF-8'); ?>)</p>
<p>BOID: <?php echo htmlspecialchars($demat['boid'], ENT_QUOTES, 'UTF-8'); ?></p>
<p><a href="holdings.php?demat_id=<?php echo (int)$demat['demat_id']; ?>">View holdings</a> <a href="edit_demat.php?id=<?php echo (int)$demat['demat_id']; ?>">Edit</a> <a href="delete_demat.php?id=<?php echo (int)$demat['demat_id']; ?>">Delete</a></p>
</article>
<?php endforeach; ?>
<?php endif; ?>
</main>
</body>
</html>
