<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$dematId = $_GET['demat_id'] ?? '';
$pdo = require __DIR__ . '/config/database.php';
$stmt = $pdo->prepare('SELECT demat_id, account_nickname FROM demat_accounts WHERE demat_id = ? AND user_id = ?');
$stmt->execute([$dematId, get_current_user_id()]);
$demat = $stmt->fetch();
if (!$demat) {
    http_response_code(404);
    exit('Demat account not found.');
}
$stmt = $pdo->prepare('SELECT h.holding_id, h.quantity, h.purchase_price, h.total_investment, c.company_name, c.symbol, c.status FROM holdings h JOIN companies c ON c.company_id = h.company_id WHERE h.demat_id = ? ORDER BY c.company_name');
$stmt->execute([$dematId]);
$holdings = $stmt->fetchAll();
$total = 0;
foreach ($holdings as $holding) {
    $total += (float)$holding['total_investment'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Holdings - Portfolio Management System</title><link rel="stylesheet" href="assets/css/style.css"></head>
<body><header><h1><?php echo htmlspecialchars($demat['account_nickname'], ENT_QUOTES, 'UTF-8'); ?> Holdings</h1><nav><a href="my_demat.php">Demat Accounts</a> <a href="add_holding.php?demat_id=<?php echo (int)$dematId; ?>">Add Holding</a> <a href="dashboard.php">Dashboard</a></nav></header><main>
<p>Total invested: <?php echo number_format($total, 2); ?></p>
<?php if (!$holdings): ?><p>No holdings have been added.</p><?php else: ?>
<?php foreach ($holdings as $holding): ?>
<article><h2><?php echo htmlspecialchars($holding['company_name'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars($holding['symbol'], ENT_QUOTES, 'UTF-8'); ?>)</h2><p>Quantity: <?php echo htmlspecialchars($holding['quantity'], ENT_QUOTES, 'UTF-8'); ?></p><p>Purchase price: <?php echo htmlspecialchars($holding['purchase_price'], ENT_QUOTES, 'UTF-8'); ?></p><p>Total invested: <?php echo htmlspecialchars($holding['total_investment'], ENT_QUOTES, 'UTF-8'); ?></p><?php if ($holding['status'] !== 'active'): ?><p><?php echo htmlspecialchars($holding['status'], ENT_QUOTES, 'UTF-8'); ?> company</p><?php endif; ?><a href="edit_holding.php?id=<?php echo (int)$holding['holding_id']; ?>">Edit</a> <a href="delete_holding.php?id=<?php echo (int)$holding['holding_id']; ?>">Delete</a></article>
<?php endforeach; ?>
<?php endif; ?></main></body></html>
