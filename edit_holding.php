<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$id = $_GET['id'] ?? $_POST['id'] ?? '';
$pdo = require __DIR__ . '/config/database.php';
$stmt = $pdo->prepare('SELECT h.holding_id, h.demat_id, h.quantity, h.purchase_price, c.company_name, d.account_nickname FROM holdings h JOIN demat_accounts d ON d.demat_id = h.demat_id JOIN companies c ON c.company_id = h.company_id WHERE h.holding_id = ? AND d.user_id = ?');
$stmt->execute([$id, get_current_user_id()]);
$holding = $stmt->fetch();
if (!$holding) {
    http_response_code(404);
    exit('Holding not found.');
}
$errors = [];
$quantity = trim($_POST['quantity'] ?? $holding['quantity']);
$purchasePrice = trim($_POST['purchase_price'] ?? $holding['purchase_price']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    }
    if (!preg_match('/^\d+(\.\d{1,4})?$/', $quantity) || (float)$quantity <= 0) {
        $errors[] = 'Quantity must be greater than zero.';
    }
    if (!preg_match('/^\d+(\.\d{1,2})?$/', $purchasePrice) || (float)$purchasePrice <= 0) {
        $errors[] = 'Purchase price must be greater than zero.';
    }
    if (!$errors) {
        $updateStmt = $pdo->prepare('UPDATE holdings h JOIN demat_accounts d ON d.demat_id = h.demat_id SET h.quantity = ?, h.purchase_price = ?, h.total_investment = (? * ?) WHERE h.holding_id = ? AND d.user_id = ?');
        $updateStmt->execute([$quantity, $purchasePrice, $quantity, $purchasePrice, $id, get_current_user_id()]);
        header('Location: holdings.php?demat_id=' . urlencode($holding['demat_id']));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Edit Holding - Portfolio Management System</title><link rel="stylesheet" href="assets/css/style.css"></head>
<body><header><h1>Edit Holding</h1><nav><a href="holdings.php?demat_id=<?php echo (int)$holding['demat_id']; ?>">Holdings</a> <a href="dashboard.php">Dashboard</a></nav></header><main>
<?php if ($errors): ?><div class="alert alert-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<p><?php echo htmlspecialchars($holding['company_name'], ENT_QUOTES, 'UTF-8'); ?> in <?php echo htmlspecialchars($holding['account_nickname'], ENT_QUOTES, 'UTF-8'); ?></p><form method="POST"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="id" value="<?php echo (int)$id; ?>"><label>Quantity<input name="quantity" value="<?php echo htmlspecialchars($quantity, ENT_QUOTES, 'UTF-8'); ?>" required></label><label>Purchase price<input name="purchase_price" value="<?php echo htmlspecialchars($purchasePrice, ENT_QUOTES, 'UTF-8'); ?>" required></label><button type="submit">Update Holding</button></form></main></body></html>
