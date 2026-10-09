<?php
require_once __DIR__ . '/includes/admin.php';
require_admin();

$pdo = require __DIR__ . '/config/database.php';
$summary = null;
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    } elseif (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK || $_FILES['csv']['size'] > 2000000 || strtolower(pathinfo($_FILES['csv']['name'], PATHINFO_EXTENSION)) !== 'csv') {
        $errors[] = 'Upload a CSV file smaller than 2 MB.';
    } else {
        $handle = fopen($_FILES['csv']['tmp_name'], 'rb');
        $header = $handle ? fgetcsv($handle) : false;
        $required = ['NEPSE Code', 'Company Name', 'Company Category', 'Sector', 'Status'];
        if (!$header || array_map('trim', $header) !== $required) {
            $errors[] = 'CSV headers must be: NEPSE Code, Company Name, Company Category, Sector, Status.';
        } else {
            $summary = ['imported' => 0, 'skipped' => 0, 'reasons' => []];
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) !== 5) {
                    $summary['skipped']++;
                    $summary['reasons'][] = 'A row had an unexpected number of columns.';
                    continue;
                }
                [$symbol, $name, $category, $sector, $status] = array_map('trim', $row);
                $normalizedStatus = strtolower($status) === 'true' ? 'active' : (strtolower($status) === 'false' ? 'inactive' : strtolower($status));
                if ($symbol === '' || $name === '' || $category === '' || !in_array($normalizedStatus, ['active', 'inactive', 'removed'], true)) {
                    $summary['skipped']++;
                    $summary['reasons'][] = 'A row had missing required values or an invalid status.';
                    continue;
                }
                try {
                    $stmt = $pdo->prepare('INSERT INTO companies (company_name, symbol, category, sector, status) VALUES (?, ?, ?, ?, ?)');
                    $stmt->execute([$name, $symbol, $category, $sector ?: null, $normalizedStatus]);
                    $summary['imported']++;
                } catch (PDOException $exception) {
                    $summary['skipped']++;
                    $summary['reasons'][] = 'A duplicate company name or symbol was skipped.';
                }
            }
            fclose($handle);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Import Companies - Portfolio Management System</title><link rel="stylesheet" href="assets/css/style.css"></head>
<body><header><h1>Import Companies</h1><nav><a href="companies.php">Companies</a> <a href="dashboard.php">Dashboard</a></nav></header><main>
<?php if ($errors): ?><div class="alert alert-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="POST" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>"><input type="file" name="csv" accept=".csv,text/csv" required><button type="submit">Import CSV</button></form>
<?php if ($summary): ?><h2>Import summary</h2><p>Imported: <?php echo (int)$summary['imported']; ?>; skipped: <?php echo (int)$summary['skipped']; ?></p><?php if ($summary['reasons']): ?><ul><?php foreach ($summary['reasons'] as $reason): ?><li><?php echo htmlspecialchars($reason, ENT_QUOTES, 'UTF-8'); ?></li><?php endforeach; ?></ul><?php endif; ?><?php endif; ?>
</main></body></html>
