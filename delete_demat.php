<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$id = $_GET['id'] ?? $_POST['id'] ?? '';
$pdo = require __DIR__ . '/config/database.php';
$stmt = $pdo->prepare('SELECT demat_id, account_nickname FROM demat_accounts WHERE demat_id = ? AND user_id = ?');
$stmt->execute([$id, get_current_user_id()]);
$demat = $stmt->fetch();

if (!$demat) {
    http_response_code(404);
    exit('Demat account not found.');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $deleteStmt = $pdo->prepare('DELETE FROM demat_accounts WHERE demat_id = ? AND user_id = ?');
        $deleteStmt->execute([$id, get_current_user_id()]);
        header('Location: my_demat.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Delete Demat - Portfolio Management System</title><link rel="stylesheet" href="assets/css/style.css"></head>
<body><main><h1>Delete Demat Account</h1><p>Delete <?php echo htmlspecialchars($demat['account_nickname'], ENT_QUOTES, 'UTF-8'); ?> and its holdings?</p><?php if ($error): ?><div class="alert alert-error" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?><form method="POST"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="id" value="<?php echo (int)$demat['demat_id']; ?>"><button type="submit">Delete Demat</button> <a href="my_demat.php">Cancel</a></form></main></body></html>
