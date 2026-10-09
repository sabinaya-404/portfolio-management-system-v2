<?php
require_once __DIR__ . '/includes/admin.php';
require_admin();

$pdo = require __DIR__ . '/config/database.php';
$errors = [];
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    } else {
        $userId = $_POST['user_id'] ?? '';
        $action = $_POST['action'] ?? '';
        if (!ctype_digit((string)$userId) || (int)$userId === get_current_user_id()) {
            $errors[] = 'You cannot change your own account role or status.';
        } elseif ($action === 'toggle_status') {
            $stmt = $pdo->prepare("UPDATE users SET account_status = CASE WHEN account_status = 'disabled' THEN 'active' ELSE 'disabled' END WHERE user_id = ?");
            $stmt->execute([$userId]);
            $success = 'Account status updated.';
        } elseif ($action === 'set_role' && in_array($_POST['role'] ?? '', ['user', 'admin'], true)) {
            $stmt = $pdo->prepare('UPDATE users SET role = ? WHERE user_id = ?');
            $stmt->execute([$_POST['role'], $userId]);
            $success = 'User role updated.';
        } else {
            $errors[] = 'Invalid user management action.';
        }
    }
}
$users = $pdo->query('SELECT user_id, full_name, email, role, account_status, email_verified, joined_at FROM users ORDER BY joined_at DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Manage Users - Portfolio Management System</title><link rel="stylesheet" href="assets/css/style.css"></head>
<body><header><h1>Manage Users</h1><nav><a href="dashboard.php">Dashboard</a> <a href="companies.php">Companies</a> <a href="news_admin.php">News</a></nav></header><main>
<?php if ($errors): ?><div class="alert alert-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li><?php endforeach; ?></ul></div><?php endif; ?><?php if ($success): ?><div class="alert alert-success" role="status"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
<?php foreach ($users as $user): ?><article><h2><?php echo htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8'); ?></h2><p><?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars($user['account_status'], ENT_QUOTES, 'UTF-8'); ?></p><?php if ((int)$user['user_id'] !== get_current_user_id()): ?><form method="POST"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="user_id" value="<?php echo (int)$user['user_id']; ?>"><button name="action" value="toggle_status" type="submit"><?php echo $user['account_status'] === 'disabled' ? 'Enable' : 'Disable'; ?></button><select name="role"><option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>User</option><option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option></select><button name="action" value="set_role" type="submit">Save role</button></form><?php endif; ?></article><?php endforeach; ?>
</main></body></html>
