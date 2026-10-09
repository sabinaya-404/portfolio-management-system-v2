<?php
require_once __DIR__ . '/includes/admin.php';
require_admin();

$pdo = require __DIR__ . '/config/database.php';
$errors = [];
$values = ['title' => '', 'description' => '', 'publication_date' => date('Y-m-d\TH:i'), 'source_url' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $key => $value) {
        $values[$key] = trim($_POST[$key] ?? '');
    }
    if (!validate_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    }
    if ($values['title'] === '' || strlen($values['title']) > 500) {
        $errors[] = 'A title between 1 and 500 characters is required.';
    }
    if (!filter_var($values['source_url'], FILTER_VALIDATE_URL) || !in_array(strtolower((string)parse_url($values['source_url'], PHP_URL_SCHEME)), ['http', 'https'], true)) {
        $errors[] = 'A valid HTTP or HTTPS source URL is required.';
    }
    $publicationDate = strtotime($values['publication_date']);
    if (!$publicationDate) {
        $errors[] = 'A valid publication date is required.';
    }
    if (!$errors) {
        try {
            $stmt = $pdo->prepare('INSERT INTO news (title, description, publication_date, source_url, status) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$values['title'], $values['description'], date('Y-m-d H:i:s', $publicationDate), $values['source_url'], 'pending']);
            header('Location: news_admin.php');
            exit;
        } catch (PDOException $exception) {
            $errors[] = 'Unable to save the news item.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Add News - Portfolio Management System</title><link rel="stylesheet" href="assets/css/style.css"></head>
<body><header><h1>Add News</h1><nav><a href="news_admin.php">News admin</a> <a href="dashboard.php">Dashboard</a></nav></header><main>
<?php if ($errors): ?><div class="alert alert-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="POST"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>"><label>Title<input name="title" maxlength="500" value="<?php echo htmlspecialchars($values['title'], ENT_QUOTES, 'UTF-8'); ?>" required></label><label>Description<textarea name="description"><?php echo htmlspecialchars($values['description'], ENT_QUOTES, 'UTF-8'); ?></textarea></label><label>Publication date<input type="datetime-local" name="publication_date" value="<?php echo htmlspecialchars($values['publication_date'], ENT_QUOTES, 'UTF-8'); ?>" required></label><label>Source URL<input type="url" name="source_url" value="<?php echo htmlspecialchars($values['source_url'], ENT_QUOTES, 'UTF-8'); ?>" required></label><button type="submit">Save pending news</button></form></main></body></html>
