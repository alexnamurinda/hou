<?php
declare(strict_types=1);

require_once __DIR__ . '/api/helpers.php';
require_once __DIR__ . '/api/membership-card-pdf.php';
$member = require_member();

header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'self'");

$download = ($_GET['download'] ?? '') === '1';
$requiredProfileFields = [
    'district',
    'region',
    'street_address',
    'postal_address',
    'business_name',
    'hotspot_count',
    'estimated_subscribers',
    'operation_areas',
    'current_isps',
    'national_id_number',
];
$profileQuery = db_connect()->prepare(
    'SELECT ' . implode(', ', $requiredProfileFields)
    . ' FROM member_profiles WHERE member_id = ? LIMIT 1'
);
$profileQuery->execute([$member['id']]);
$profile = $profileQuery->fetch() ?: [];
$profileComplete = count($profile) === count($requiredProfileFields);
foreach ($requiredProfileFields as $field) {
    if (!isset($profile[$field]) || trim((string)$profile[$field]) === '') {
        $profileComplete = false;
        break;
    }
}

$memberDetailsComplete = trim((string)$member['registration_number']) !== ''
    && trim((string)$member['full_name']) !== ''
    && trim((string)$member['created_at']) !== '';
if ($download && (!$profileComplete || !$memberDetailsComplete)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Complete your required profile details before downloading your membership card.');
}

try {
    $pdf = membership_card_pdf($member);
} catch (RuntimeException $error) {
    error_log('HOU membership card generation failed: ' . $error->getMessage());
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    exit('The membership card is temporarily unavailable. Please try again later.');
}

header('Content-Type: application/pdf');
header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="HOU-membership-card.pdf"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
