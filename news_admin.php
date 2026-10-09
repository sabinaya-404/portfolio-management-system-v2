<?php
require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/news.php';
require_admin();

$pdo = require __DIR__ . '/config/database.php';
$errors = [];
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    } elseif (($_POST['action'] ?? '') === 'import') {
        $result = import_configured_rss($pdo);
        if ($result['error']) {
            $errors[] = $result['error'];
        } else {
            $success = 'Imported ' . $result['imported'] . ' item(s); skipped ' . $result['skipped'] . '.';
        }
    } elseif (($_POST['action'] ?? '') === 'status' && ctype_digit((string)($_POST['news_id'] ?? '')) && in_array($_POST['status'] ?? '', ['published', 'unpublished', 'pending'], true)) {
        $stmt = $pdo->prepare('UPDATE news SET status = ? WHERE news_id = ?');
        $stmt->execute([$_POST['status'], $_POST['news_id']]);
        $success = 'News status updated.';
    } else {
        $errors[] = 'Invalid news action.';
    }
}
$news = $pdo->query('SELECT news_id, title, publication_date, source_url, status FROM news ORDER BY publication_date DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Manage News - Portfolio Management System</title><link rel="stylesheet" href="assets/css/style.css"></head>
<body><header><h1>Manage News</h1><nav><a href="dashboard.php">Dashboard</a> <a href="companies.php">Companies</a> <a href="manage_users.php">Users</a> <a href="news.php">Public news</a></nav></header><main>
<?php if ($errors): ?><div class="alert alert-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li><?php endforeach; ?></ul></div><?php endif; ?><?php if ($success): ?><div class="alert alert-success" role="status"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>"><button name="action" value="import" type="submit">Import configured RSS feed</button></form>
<?php foreach ($news as $item): ?><article><h2><?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?></h2><p><?php echo htmlspecialchars($item['publication_date'], ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars($item['status'], ENT_QUOTES, 'UTF-8'); ?></p><a href="<?php echo htmlspecialchars($item['source_url'], ENT_QUOTES, 'UTF-8'); ?>" rel="noopener noreferrer">Source</a><form method="POST"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="news_id" value="<?php echo (int)$item['news_id']; ?>"><select name="status"><?php foreach (['pending', 'published', 'unpublished'] as $status): ?><option value="<?php echo $status; ?>" <?php echo $item['status'] === $status ? 'selected' : ''; ?>><?php echo ucfirst($status); ?></option><?php endforeach; ?></select><button name="action" value="status" type="submit">Save status</button></form></article><?php endforeach; ?>
</main></body></html>
