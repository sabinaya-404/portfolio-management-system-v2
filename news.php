<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$pdo = require __DIR__ . '/config/database.php';
$news = $pdo->query("SELECT title, description, publication_date, source_url FROM news WHERE status = 'published' ORDER BY publication_date DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>News - Portfolio Management System</title><link rel="stylesheet" href="assets/css/style.css"></head>
<body><header><h1>News</h1><nav><a href="dashboard.php">Dashboard</a> <a href="my_demat.php">Demat Accounts</a> <a href="logout.php">Logout</a></nav></header><main>
<?php if (!$news): ?><p>No published news is available.</p><?php else: ?><?php foreach ($news as $item): ?><article><h2><?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?></h2><p><?php echo htmlspecialchars($item['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p><p><?php echo htmlspecialchars($item['publication_date'], ENT_QUOTES, 'UTF-8'); ?> <a href="<?php echo htmlspecialchars($item['source_url'], ENT_QUOTES, 'UTF-8'); ?>" rel="noopener noreferrer">Read source</a></p></article><?php endforeach; ?><?php endif; ?>
</main></body></html>
