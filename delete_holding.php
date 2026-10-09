<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$id = $_GET['id'] ?? $_POST['id'] ?? '';
$pdo = require __DIR__ . '/config/database.php';
$stmt = $pdo->prepare('SELECT h.holding_id, h.demat_id, c.company_name FROM holdings h JOIN demat_accounts d ON d.demat_id = h.demat_id JOIN companies c ON c.company_id = h.company_id WHERE h.holding_id = ? AND d.user_id = ?');
$stmt->execute([$id, get_current_user_id()]);
$holding = $stmt->fetch();
if (!$holding) {
    http_response_code(404);
    exit('Holding not found.');
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $deleteStmt = $pdo->prepare('DELETE h FROM holdings h JOIN demat_accounts d ON d.demat_id = h.demat_id WHERE h.holding_id = ? AND d.user_id = ?');
        $deleteStmt->execute([$id, get_current_user_id()]);
        header('Location: holdings.php?demat_id=' . urlencode($holding['demat_id']));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Delete Holding - Portfolio Management System</title><link rel="stylesheet" href="assets/css/style.css"></head>
<body><main><h1>Delete Holding</h1><p>Delete <?php echo htmlspecialchars($holding['company_name'], ENT_QUOTES, 'UTF-8'); ?>?</p><?php if ($error): ?><div class="alert alert-error" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?><form method="POST"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="id" value="<?php echo (int)$id; ?>"><button type="submit">Delete Holding</button> <a href="holdings.php?demat_id=<?php echo (int)$holding['demat_id']; ?>">Cancel</a></form></main></body></html>
