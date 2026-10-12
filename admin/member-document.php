<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../client/config.php';
require_admin();

header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'self'");

$memberId = filter_var($_GET['member'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$documentType = $_GET['type'] ?? '';
$documents = [
    'certificate' => ['business_certificate_data', 'business_certificate_mime', 'business_certificate_path'],
    'national-id' => ['national_id_photo_data', 'national_id_photo_mime', 'national_id_photo_path'],
];
if ($memberId === false || $memberId === null || !is_string($documentType) || !isset($documents[$documentType])) {
    http_response_code(404);
    exit('Document not found.');
}

[$dataColumn, $mimeColumn, $pathColumn] = $documents[$documentType];
$query = db_connect()->prepare(
    'SELECT ' . $dataColumn . ', ' . $mimeColumn . ', ' . $pathColumn
    . ' FROM member_profiles WHERE member_id = ? LIMIT 1'
);
$query->execute([$memberId]);
$document = $query->fetch();
if (!$document) {
    http_response_code(404);
    exit('Document not found.');
}

$data = $document[$dataColumn] ?? null;
$mimeType = $document[$mimeColumn] ?? null;
if (!is_string($data) || !is_string($mimeType)) {
    $legacyPath = $document[$pathColumn] ?? null;
    if (
        !is_string($legacyPath)
        || !preg_match('~^private_uploads/[a-f0-9]{48}\.(pdf|jpg|png)$~', $legacyPath, $matches)
    ) {
        http_response_code(404);
        exit('Document not found.');
    }

    $uploadRoot = realpath(__DIR__ . '/../client/private_uploads');
    $filePath = realpath(__DIR__ . '/../client/' . $legacyPath);
    if (
        $uploadRoot === false
        || $filePath === false
        || !str_starts_with($filePath, $uploadRoot . DIRECTORY_SEPARATOR)
        || !is_file($filePath)
    ) {
        http_response_code(404);
        exit('Document not found.');
    }

    $legacyMimes = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'png' => 'image/png'];
    $mimeType = $legacyMimes[$matches[1]];
    $data = file_get_contents($filePath);
}

$allowedMimes = ['application/pdf', 'image/jpeg', 'image/png'];
if (!is_string($data) || !in_array($mimeType, $allowedMimes, true)) {
    http_response_code(404);
    exit('Document not found.');
}

$extension = match ($mimeType) {
    'application/pdf' => 'pdf',
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
};
$filename = $documentType . '.' . $extension;
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . strlen($data));
header('Content-Disposition: inline; filename="' . $filename . '"');
echo $data;
