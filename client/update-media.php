<?php
declare(strict_types=1);

require_once __DIR__ . '/api/helpers.php';
require_member();

header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

$updateId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($updateId === false || $updateId === null) {
    http_response_code(404);
    exit;
}

$query = db_connect()->prepare(
    "SELECT image_data, image_mime
     FROM member_announcements
     WHERE id = ? AND status = 'published' AND (published_at IS NULL OR published_at <= NOW())
     LIMIT 1"
);
$query->execute([$updateId]);
$media = $query->fetch();
$allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
if (
    !$media
    || !is_string($media['image_mime'] ?? null)
    || !in_array($media['image_mime'], $allowedMimeTypes, true)
    || $media['image_data'] === null
) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $media['image_mime']);
header('Content-Length: ' . strlen((string)$media['image_data']));
header('Content-Disposition: inline');
echo $media['image_data'];
