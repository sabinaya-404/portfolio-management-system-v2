<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$pdo = require __DIR__ . '/config/database.php';
$errors = [];
$values = ['account_nickname' => '', 'account_holder_name' => '', 'dp_broker_name' => '', 'dp_broker_number' => '', 'boid' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $key => $value) {
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
            $pdo->beginTransaction();
            $countStmt = $pdo->prepare('SELECT COUNT(*) FROM demat_accounts WHERE user_id = ? FOR UPDATE');
            $countStmt->execute([get_current_user_id()]);
            if ((int)$countStmt->fetchColumn() >= 10) {
                throw new RuntimeException('You can have a maximum of 10 Demat accounts.');
            }
            $stmt = $pdo->prepare('INSERT INTO demat_accounts (user_id, account_nickname, account_holder_name, dp_broker_name, dp_broker_number, boid) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([get_current_user_id(), $values['account_nickname'], $values['account_holder_name'], $values['dp_broker_name'], $values['dp_broker_number'], $values['boid']]);
            $pdo->commit();
            header('Location: my_demat.php');
            exit;
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = $exception->getCode() === '23000' ? 'Demat acc holder with this BOID already exists' : 'Unable to save the Demat account.';
        } catch (RuntimeException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = $exception->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Add Demat - Portfolio Management System</title><link rel="stylesheet" href="assets/css/style.css"></head>
<body><header><h1>Add Demat Account</h1><nav><a href="my_demat.php">Demat Accounts</a> <a href="dashboard.php">Dashboard</a></nav></header><main>
<?php if ($errors): ?><div class="alert alert-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
<label>Account nickname<input name="account_nickname" maxlength="100" value="<?php echo htmlspecialchars($values['account_nickname'], ENT_QUOTES, 'UTF-8'); ?>" required></label>
<label>Account holder name<input name="account_holder_name" maxlength="100" value="<?php echo htmlspecialchars($values['account_holder_name'], ENT_QUOTES, 'UTF-8'); ?>" required></label>
<label>DP/Broker name<input name="dp_broker_name" maxlength="150" value="<?php echo htmlspecialchars($values['dp_broker_name'], ENT_QUOTES, 'UTF-8'); ?>" required></label>
<label>DP/Broker number<input name="dp_broker_number" maxlength="100" value="<?php echo htmlspecialchars($values['dp_broker_number'], ENT_QUOTES, 'UTF-8'); ?>" required></label>
<label>BOID<input name="boid" maxlength="16" pattern="\d{16}" value="<?php echo htmlspecialchars($values['boid'], ENT_QUOTES, 'UTF-8'); ?>" required></label>
<button type="submit">Save Demat</button></form></main></body></html>
