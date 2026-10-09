<?php
require_once __DIR__ . '/includes/admin.php';
require_admin();

$id = $_GET['id'] ?? $_POST['id'] ?? '';
$pdo = require __DIR__ . '/config/database.php';
$stmt = $pdo->prepare('SELECT company_id, company_name, symbol, category, sector, status FROM companies WHERE company_id = ?');
$stmt->execute([$id]);
$company = $stmt->fetch();
if (!$company) {
    http_response_code(404);
    exit('Company not found.');
}
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['company_name', 'symbol', 'category', 'sector', 'status'] as $key) {
        $company[$key] = trim($_POST[$key] ?? '');
    }
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    }
    if ($company['company_name'] === '' || $company['symbol'] === '' || $company['category'] === '') {
        $errors[] = 'Company name, symbol, and category are required.';
    }
    if (!in_array($company['status'], ['active', 'inactive', 'removed'], true)) {
        $errors[] = 'Invalid company status.';
    }
    if (!$errors) {
        try {
            $stmt = $pdo->prepare('UPDATE companies SET company_name = ?, symbol = ?, category = ?, sector = ?, status = ? WHERE company_id = ?');
            $stmt->execute([$company['company_name'], $company['symbol'], $company['category'], $company['sector'] ?: null, $company['status'], $id]);
            header('Location: companies.php');
            exit;
        } catch (PDOException $exception) {
            $errors[] = $exception->getCode() === '23000' ? 'Company name or symbol already exists.' : 'Unable to update the company.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Edit Company - Portfolio Management System</title><link rel="stylesheet" href="assets/css/style.css"></head>
<body><header><h1>Edit Company</h1><nav><a href="companies.php">Companies</a> <a href="dashboard.php">Dashboard</a></nav></header><main>
<?php if ($errors): ?><div class="alert alert-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="id" value="<?php echo (int)$company['company_id']; ?>"><label>Company name<input name="company_name" maxlength="255" value="<?php echo htmlspecialchars($company['company_name'], ENT_QUOTES, 'UTF-8'); ?>" required></label><label>Symbol<input name="symbol" maxlength="50" value="<?php echo htmlspecialchars($company['symbol'], ENT_QUOTES, 'UTF-8'); ?>" required></label><label>Category<input name="category" maxlength="100" value="<?php echo htmlspecialchars($company['category'], ENT_QUOTES, 'UTF-8'); ?>" required></label><label>Sector<input name="sector" maxlength="100" value="<?php echo htmlspecialchars($company['sector'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label><label>Status<select name="status"><?php foreach (['active', 'inactive', 'removed'] as $status): ?><option value="<?php echo $status; ?>" <?php echo $company['status'] === $status ? 'selected' : ''; ?>><?php echo ucfirst($status); ?></option><?php endforeach; ?></select></label><button type="submit">Update company</button></form></main></body></html>
