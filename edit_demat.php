<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$id = $_GET['id'] ?? $_POST['id'] ?? '';
$pdo = require __DIR__ . '/config/database.php';
$stmt = $pdo->prepare('SELECT demat_id, account_nickname, account_holder_name, dp_broker_name, dp_broker_number, boid FROM demat_accounts WHERE demat_id = ? AND user_id = ?');
$stmt->execute([$id, get_current_user_id()]);
$demat = $stmt->fetch();
$errors = [];

if (!$demat) {
    http_response_code(404);
    exit('Demat account not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values = [];
    foreach (['account_nickname', 'account_holder_name', 'dp_broker_name', 'dp_broker_number', 'boid'] as $key) {
        $values[$key] = trim($_POST[$key] ?? '');
    }
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    }
    foreach (['account_nickname' => 'Account nickname', 'account_holder_name' => 'Account holder name', 'dp_broker_name' => 'DP/Broker name', 'dp_broker_number' => 'DP/Broker number'] as $key => $label) {
        if ($values[$key] === '') {
            $errors[] = $label . ' is required.';
        }
    }
    if (!preg_match('/^\d{16}$/', $values['boid'])) {
        $errors[] = 'BOID must contain exactly 16 digits.';
    }
    if (!$errors) {
        try {
            $stmt = $pdo->prepare('UPDATE demat_accounts SET account_nickname = ?, account_holder_name = ?, dp_broker_name = ?, dp_broker_number = ?, boid = ? WHERE demat_id = ? AND user_id = ?');
            $stmt->execute([$values['account_nickname'], $values['account_holder_name'], $values['dp_broker_name'], $values['dp_broker_number'], $values['boid'], $id, get_current_user_id()]);
            header('Location: my_demat.php');
            exit;
        } catch (PDOException $exception) {
            $errors[] = $exception->getCode() === '23000' ? 'Demat acc holder with this BOID already exists' : 'Unable to update the Demat account.';
        }
    }
    $demat = array_merge($demat, $values);
}
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Edit Demat - Portfolio Management System</title><link rel="stylesheet" href="assets/css/style.css"></head>
<body><header><h1>Edit Demat Account</h1><nav><a href="my_demat.php">Demat Accounts</a> <a href="dashboard.php">Dashboard</a></nav></header><main>
<?php if ($errors): ?><div class="alert alert-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="id" value="<?php echo (int)$demat['demat_id']; ?>">
<label>Account nickname<input name="account_nickname" maxlength="100" value="<?php echo htmlspecialchars($demat['account_nickname'], ENT_QUOTES, 'UTF-8'); ?>" required></label>
<label>Account holder name<input name="account_holder_name" maxlength="100" value="<?php echo htmlspecialchars($demat['account_holder_name'], ENT_QUOTES, 'UTF-8'); ?>" required></label>
<label>DP/Broker name<input name="dp_broker_name" maxlength="150" value="<?php echo htmlspecialchars($demat['dp_broker_name'], ENT_QUOTES, 'UTF-8'); ?>" required></label>
<label>DP/Broker number<input name="dp_broker_number" maxlength="100" value="<?php echo htmlspecialchars($demat['dp_broker_number'], ENT_QUOTES, 'UTF-8'); ?>" required></label>
<label>BOID<input name="boid" maxlength="16" pattern="\d{16}" value="<?php echo htmlspecialchars($demat['boid'], ENT_QUOTES, 'UTF-8'); ?>" required></label>
<button type="submit">Update Demat</button></form></main></body></html>
