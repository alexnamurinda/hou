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
    || !isset($media['image_data'])
) {
    http_response_code(404);
    exit;
}

$imageData = $media['image_data'];
if (is_resource($imageData)) {
    $imageData = stream_get_contents($imageData);
}
if (!is_string($imageData) || $imageData === '') {
    error_log('HOU update image is empty or unreadable for update ' . $updateId . '.');
    http_response_code(404);
    exit;
}

$imageMime = (new finfo(FILEINFO_MIME_TYPE))->buffer($imageData);
if (
    !is_string($imageMime)
    || !in_array($imageMime, $allowedMimeTypes, true)
    || !is_array(getimagesizefromstring($imageData))
) {
    error_log('HOU update image has invalid image data for update ' . $updateId . '.');
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $imageMime);
header('Content-Length: ' . strlen($imageData));
header('Content-Disposition: inline');
echo $imageData;
