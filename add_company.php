<?php
require_once __DIR__ . '/includes/admin.php';
require_admin();

$pdo = require __DIR__ . '/config/database.php';
$errors = [];
$values = ['company_name' => '', 'symbol' => '', 'category' => '', 'sector' => '', 'status' => 'active'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $key => $value) {
        $values[$key] = trim($_POST[$key] ?? '');
    }
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    }
    foreach (['company_name' => 'Company name', 'symbol' => 'Symbol', 'category' => 'Category'] as $key => $label) {
        if ($values[$key] === '') {
            $errors[] = $label . ' is required.';
        }
    }
    if (!in_array($values['status'], ['active', 'inactive', 'removed'], true)) {
        $errors[] = 'Invalid company status.';
    }
    if (!$errors) {
        try {
            $stmt = $pdo->prepare('INSERT INTO companies (company_name, symbol, category, sector, status) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$values['company_name'], $values['symbol'], $values['category'], $values['sector'] ?: null, $values['status']]);
            header('Location: companies.php');
            exit;
        } catch (PDOException $exception) {
            $errors[] = $exception->getCode() === '23000' ? 'Company name or symbol already exists.' : 'Unable to save the company.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Add Company - Portfolio Management System</title><link rel="stylesheet" href="assets/css/style.css"></head>
<body><header><h1>Add Company</h1><nav><a href="companies.php">Companies</a> <a href="dashboard.php">Dashboard</a></nav></header><main>
<?php if ($errors): ?><div class="alert alert-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>"><label>Company name<input name="company_name" maxlength="255" value="<?php echo htmlspecialchars($values['company_name'], ENT_QUOTES, 'UTF-8'); ?>" required></label><label>Symbol<input name="symbol" maxlength="50" value="<?php echo htmlspecialchars($values['symbol'], ENT_QUOTES, 'UTF-8'); ?>" required></label><label>Category<input name="category" maxlength="100" value="<?php echo htmlspecialchars($values['category'], ENT_QUOTES, 'UTF-8'); ?>" required></label><label>Sector<input name="sector" maxlength="100" value="<?php echo htmlspecialchars($values['sector'], ENT_QUOTES, 'UTF-8'); ?>"></label><label>Status<select name="status"><option value="active">Active</option><option value="inactive">Inactive</option><option value="removed">Removed</option></select></label><button type="submit">Save company</button></form></main></body></html>
