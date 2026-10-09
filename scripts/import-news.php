<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/news.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();
$pdo = require __DIR__ . '/../config/database.php';
$result = import_configured_rss($pdo);
if ($result['error']) {
    fwrite(STDERR, $result['error'] . PHP_EOL);
    exit(1);
}
fwrite(STDOUT, 'Imported ' . $result['imported'] . '; skipped ' . $result['skipped'] . PHP_EOL);
