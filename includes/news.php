<?php
function import_configured_rss(PDO $pdo): array
{
    $feedUrl = trim($_ENV['RSS_FEED_URL'] ?? '');
    if (!filter_var($feedUrl, FILTER_VALIDATE_URL) || !in_array(strtolower((string)parse_url($feedUrl, PHP_URL_SCHEME)), ['http', 'https'], true)) {
        return ['imported' => 0, 'skipped' => 0, 'error' => 'RSS_FEED_URL is not configured with a valid HTTP or HTTPS URL.'];
    }
    $context = stream_context_create(['http' => ['timeout' => 10, 'user_agent' => 'PMS V2 RSS importer']]);
    $xmlContent = @file_get_contents($feedUrl, false, $context);
    if ($xmlContent === false) {
        return ['imported' => 0, 'skipped' => 0, 'error' => 'The RSS feed could not be retrieved.'];
    }
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($xmlContent);
    if ($xml === false) {
        return ['imported' => 0, 'skipped' => 0, 'error' => 'The RSS feed response was malformed.'];
    }
    $items = $xml->channel->item ?? $xml->entry ?? [];
    $imported = 0;
    $skipped = 0;
    foreach ($items as $item) {
        $title = trim((string)($item->title ?? ''));
        $description = trim((string)($item->description ?? $item->summary ?? ''));
        $sourceUrl = trim((string)($item->link['href'] ?? $item->link ?? ''));
        $published = trim((string)($item->pubDate ?? $item->published ?? $item->updated ?? ''));
        $date = strtotime($published);
        if ($title === '' || !filter_var($sourceUrl, FILTER_VALIDATE_URL) || !$date) {
            $skipped++;
            continue;
        }
        $check = $pdo->prepare('SELECT news_id FROM news WHERE source_url = ? OR (title = ? AND publication_date = ?) LIMIT 1');
        $publicationDate = date('Y-m-d H:i:s', $date);
        $check->execute([$sourceUrl, $title, $publicationDate]);
        if ($check->fetch()) {
            $skipped++;
            continue;
        }
        $insert = $pdo->prepare('INSERT INTO news (title, description, publication_date, source_url, status) VALUES (?, ?, ?, ?, ?)');
        $insert->execute([$title, strip_tags($description), $publicationDate, $sourceUrl, 'pending']);
        $imported++;
    }
    return ['imported' => $imported, 'skipped' => $skipped, 'error' => ''];
}
