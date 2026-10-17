<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';

$member = require_member();
$updateId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($updateId === false || $updateId === null) {
    http_response_code(404);
    exit;
}

$query = db_connect()->prepare(
    "SELECT attachment_data, attachment_filename
     FROM member_announcements
     WHERE id = ? AND status = 'published' AND (published_at IS NULL OR published_at <= NOW())
     LIMIT 1"
);
$query->execute([$updateId]);
$attachment = $query->fetch();
if (
    !$attachment
    || !is_string($attachment['attachment_data'])
    || $attachment['attachment_data'] === ''
    || !is_string($attachment['attachment_filename'])
) {
    http_response_code(404);
    exit;
}

$filename = basename(str_replace('\\', '/', $attachment['attachment_filename']));
$asciiFilename = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);
if (!is_string($asciiFilename) || $asciiFilename === '') {
    $asciiFilename = 'update-attachment.pdf';
}

header('Content-Type: application/pdf');
header('Content-Length: ' . strlen($attachment['attachment_data']));
header('Content-Disposition: attachment; filename="' . $asciiFilename . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
echo $attachment['attachment_data'];
