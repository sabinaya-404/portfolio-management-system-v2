<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$dematId = $_GET['demat_id'] ?? $_POST['demat_id'] ?? '';
$pdo = require __DIR__ . '/config/database.php';
$stmt = $pdo->prepare('SELECT demat_id, account_nickname FROM demat_accounts WHERE demat_id = ? AND user_id = ?');
$stmt->execute([$dematId, get_current_user_id()]);
$demat = $stmt->fetch();
if (!$demat) {
    http_response_code(404);
    exit('Demat account not found.');
}
$companies = $pdo->query("SELECT company_id, company_name, symbol FROM companies WHERE status = 'active' ORDER BY company_name")->fetchAll();
$errors = [];
$companyId = $_POST['company_id'] ?? '';
$quantity = trim($_POST['quantity'] ?? '');
$purchasePrice = trim($_POST['purchase_price'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    }
    if (!ctype_digit((string)$companyId)) {
        $errors[] = 'Select a valid company.';
    } else {
        $companyStmt = $pdo->prepare("SELECT company_id FROM companies WHERE company_id = ? AND status = 'active'");
        $companyStmt->execute([$companyId]);
        if (!$companyStmt->fetch()) {
            $errors[] = 'Select an active company.';
        }
    }
    if (!preg_match('/^\d+(\.\d{1,4})?$/', $quantity) || (float)$quantity <= 0) {
        $errors[] = 'Quantity must be greater than zero.';
    }
    if (!preg_match('/^\d+(\.\d{1,2})?$/', $purchasePrice) || (float)$purchasePrice <= 0) {
        $errors[] = 'Purchase price must be greater than zero.';
    }
    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $findStmt = $pdo->prepare('SELECT holding_id FROM holdings WHERE demat_id = ? AND company_id = ? FOR UPDATE');
            $findStmt->execute([$dematId, $companyId]);
            $existing = $findStmt->fetch();
            if ($existing) {
                $updateStmt = $pdo->prepare('UPDATE holdings SET purchase_price = (total_investment + (? * ?)) / (quantity + ?), quantity = quantity + ?, total_investment = total_investment + (? * ?) WHERE holding_id = ?');
                $updateStmt->execute([$quantity, $purchasePrice, $quantity, $quantity, $quantity, $purchasePrice, $existing['holding_id']]);
            } else {
                $insertStmt = $pdo->prepare('INSERT INTO holdings (demat_id, company_id, quantity, purchase_price, total_investment) VALUES (?, ?, ?, ?, (? * ?))');
                $insertStmt->execute([$dematId, $companyId, $quantity, $purchasePrice, $quantity, $purchasePrice]);
            }
            $pdo->commit();
            header('Location: holdings.php?demat_id=' . urlencode($dematId));
            exit;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Unable to save the holding.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Add Holding - Portfolio Management System</title><link rel="stylesheet" href="assets/css/style.css"></head>
<body><header><h1>Add Holding to <?php echo htmlspecialchars($demat['account_nickname'], ENT_QUOTES, 'UTF-8'); ?></h1><nav><a href="holdings.php?demat_id=<?php echo (int)$dematId; ?>">Holdings</a> <a href="dashboard.php">Dashboard</a></nav></header><main>
<?php if ($errors): ?><div class="alert alert-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="demat_id" value="<?php echo (int)$dematId; ?>"><label>Company<select name="company_id" required><option value="">Select company</option><?php foreach ($companies as $company): ?><option value="<?php echo (int)$company['company_id']; ?>" <?php echo (string)$companyId === (string)$company['company_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($company['company_name'] . ' (' . $company['symbol'] . ')', ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></label><label>Quantity<input name="quantity" value="<?php echo htmlspecialchars($quantity, ENT_QUOTES, 'UTF-8'); ?>" required></label><label>Purchase price<input name="purchase_price" value="<?php echo htmlspecialchars($purchasePrice, ENT_QUOTES, 'UTF-8'); ?>" required></label><button type="submit">Save Holding</button></form></main></body></html>
