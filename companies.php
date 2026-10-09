<?php
require_once __DIR__ . '/includes/admin.php';
require_admin();

$pdo = require __DIR__ . '/config/database.php';
$companies = $pdo->query('SELECT company_id, company_name, symbol, category, sector, status FROM companies ORDER BY company_name')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Companies - Portfolio Management System</title><link rel="stylesheet" href="assets/css/style.css"></head>
<body><header><h1>Companies</h1><nav><a href="dashboard.php">Dashboard</a> <a href="add_company.php">Add company</a> <a href="import_companies.php">Import CSV</a> <a href="manage_users.php">Users</a> <a href="news_admin.php">News</a></nav></header><main>
<?php if (!$companies): ?><p>No companies have been added.</p><?php else: ?><ul><?php foreach ($companies as $company): ?><li><?php echo htmlspecialchars($company['company_name'] . ' (' . $company['symbol'] . ') - ' . $company['status'], ENT_QUOTES, 'UTF-8'); ?> <a href="edit_company.php?id=<?php echo (int)$company['company_id']; ?>">Edit</a></li><?php endforeach; ?></ul><?php endif; ?>
</main></body></html>
