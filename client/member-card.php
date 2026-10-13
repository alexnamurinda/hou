<?php
declare(strict_types=1);

require_once __DIR__ . '/api/helpers.php';
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

function membership_card_pdf_escape(string $text): string
{
    $encoded = mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
    return strtr($encoded, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']);
}

function membership_card_pdf_text(
    float $x,
    float $y,
    float $size,
    string $text,
    string $font = 'F1',
    array $color = [0.06, 0.15, 0.34]
): string {
    [$red, $green, $blue] = $color;
    return sprintf(
        "%.3f %.3f %.3f rg\nBT /%s %.2f Tf %.2f %.2f Td (%s) Tj ET\n",
        $red,
        $green,
        $blue,
        $font,
        $size,
        $x,
        $y,
        membership_card_pdf_escape($text)
    );
}

function membership_card_pdf_rect(float $x, float $y, float $width, float $height, array $color): string
{
    [$red, $green, $blue] = $color;
    return sprintf("%.3f %.3f %.3f rg %.2f %.2f %.2f %.2f re f\n", $red, $green, $blue, $x, $y, $width, $height);
}

function membership_card_pdf_circle(float $x, float $y, float $radius, array $color): string
{
    [$red, $green, $blue] = $color;
    $control = $radius * 0.55228475;
    return sprintf(
        "%.3f %.3f %.3f rg %.2f %.2f m %.2f %.2f %.2f %.2f %.2f %.2f c "
        . "%.2f %.2f %.2f %.2f %.2f %.2f c %.2f %.2f %.2f %.2f %.2f %.2f c "
        . "%.2f %.2f %.2f %.2f %.2f %.2f c f\n",
        $red,
        $green,
        $blue,
        $x + $radius,
        $y,
        $x + $radius,
        $y + $control,
        $x + $control,
        $y + $radius,
        $x,
        $y + $radius,
        $x - $control,
        $y + $radius,
        $x - $radius,
        $y + $control,
        $x - $radius,
        $y,
        $x - $radius,
        $y - $control,
        $x - $control,
        $y - $radius,
        $x,
        $y - $radius,
        $x + $control,
        $y - $radius,
        $x + $radius,
        $y - $control,
        $x + $radius,
        $y
    );
}

function membership_card_pdf_curve(
    float $x1,
    float $y1,
    float $controlX1,
    float $controlY1,
    float $controlX2,
    float $controlY2,
    float $x2,
    float $y2,
    array $color,
    float $lineWidth
): string {
    [$red, $green, $blue] = $color;
    return sprintf(
        "%.3f %.3f %.3f RG %.2f w %.2f %.2f m %.2f %.2f %.2f %.2f %.2f %.2f c S\n",
        $red,
        $green,
        $blue,
        $lineWidth,
        $x1,
        $y1,
        $controlX1,
        $controlY1,
        $controlX2,
        $controlY2,
        $x2,
        $y2
    );
}

function membership_card_pdf(float $pageWidth, float $pageHeight, string $content): string
{
    $contentLength = strlen($content);
    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '
            . $pageWidth . ' ' . $pageHeight
            . '] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
        "<< /Length $contentLength >>\nstream\n$content" . "endstream",
    ];

    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $index => $object) {
        $offsets[] = strlen($pdf);
        $pdf .= ($index + 1) . " 0 obj\n" . $object . "\nendobj\n";
    }
    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
    foreach (array_slice($offsets, 1) as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }
    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n"
        . "startxref\n$xrefOffset\n%%EOF\n";
    return $pdf;
}

$membershipDate = trim((string)$member['created_at']) !== ''
    ? (new DateTimeImmutable((string)$member['created_at']))->format('M Y')
    : 'Not provided';
$memberName = trim((string)$member['full_name']) !== ''
    ? mb_strtoupper((string)$member['full_name'], 'UTF-8')
    : 'Not provided';
$registrationNumber = trim((string)$member['registration_number']) !== ''
    ? (string)$member['registration_number']
    : 'Not provided';
$pdfName = membership_card_pdf_escape($memberName);
$nameFontSize = min(27, max(12, 350 / max(1, mb_strwidth($pdfName, 'Windows-1252'))));

$content = membership_card_pdf_rect(0, 0, 792, 480, [0.96, 0.97, 0.99]);
$content .= membership_card_pdf_rect(0, 326, 792, 154, [0.99, 0.99, 1.0]);
$content .= membership_card_pdf_rect(0, 148, 792, 178, [0.02, 0.30, 0.64]);
$content .= membership_card_pdf_rect(70, 237, 40, 4, [1.0, 0.62, 0.02]);
$content .= membership_card_pdf_rect(110, 237, 250, 4, [0.23, 0.67, 0.94]);

$content .= membership_card_pdf_circle(107, 414, 11, [0.02, 0.37, 0.75]);
$content .= membership_card_pdf_circle(83, 393, 8, [0.02, 0.37, 0.75]);
$content .= membership_card_pdf_circle(131, 393, 8, [0.02, 0.37, 0.75]);
$content .= membership_card_pdf_curve(65, 394, 71, 408, 87, 408, 94, 394, [0.02, 0.37, 0.75], 5);
$content .= membership_card_pdf_curve(119, 394, 126, 408, 142, 408, 149, 394, [0.02, 0.37, 0.75], 5);
$content .= membership_card_pdf_curve(78, 376, 91, 396, 123, 396, 136, 376, [0.02, 0.37, 0.75], 7);
$content .= membership_card_pdf_curve(86, 432, 98, 444, 116, 444, 128, 432, [0.02, 0.37, 0.75], 5);
$content .= membership_card_pdf_curve(76, 441, 95, 459, 119, 459, 138, 441, [0.02, 0.37, 0.75], 5);
$content .= membership_card_pdf_text(168, 410, 31, 'HOT', 'F2', [0.02, 0.37, 0.75]);
$content .= membership_card_pdf_text(229, 410, 31, 'NET', 'F2', [1.0, 0.55, 0.0]);
$content .= membership_card_pdf_text(169, 388, 14, 'HOTSPOT OPERATORS NETWORK UGANDA', 'F2', [0.07, 0.25, 0.49]);
$content .= membership_card_pdf_circle(704, 405, 49, [0.91, 0.93, 0.96]);
$content .= membership_card_pdf_circle(704, 417, 17, [0.29, 0.34, 0.40]);
$content .= membership_card_pdf_curve(667, 378, 676, 401, 732, 401, 741, 378, [0.29, 0.34, 0.40], 20);

$content .= membership_card_pdf_text(70, 257, 38, 'MEMBERSHIP', 'F2', [1.0, 1.0, 1.0]);
$content .= membership_card_pdf_text(350, 257, 38, 'CARD', 'F2', [1.0, 0.62, 0.02]);
$content .= membership_card_pdf_text(70, 181, 28, 'ID:', 'F1', [1.0, 1.0, 1.0]);
$content .= membership_card_pdf_text(142, 175, 42, $registrationNumber, 'F2', [1.0, 1.0, 1.0]);
$content .= membership_card_pdf_curve(610, 183, 663, 238, 729, 238, 792, 275, [0.15, 0.49, 0.80], 26);
$content .= membership_card_pdf_curve(650, 150, 698, 201, 750, 201, 792, 228, [0.15, 0.49, 0.80], 25);
$content .= membership_card_pdf_curve(694, 126, 727, 160, 764, 168, 792, 183, [0.15, 0.49, 0.80], 24);

$content .= membership_card_pdf_text(70, 108, 17, 'NAME:', 'F2', [0.32, 0.37, 0.44]);
$content .= membership_card_pdf_text(70, 65, $nameFontSize, $memberName, 'F2', [0.05, 0.22, 0.47]);
$content .= membership_card_pdf_text(430, 108, 17, 'SINCE:', 'F2', [0.32, 0.37, 0.44]);
$content .= membership_card_pdf_text(430, 65, 27, strtoupper($membershipDate), 'F2', [0.05, 0.22, 0.47]);
$content .= "0.75 0.78 0.82 RG 1.5 w 14 8 764 464 re S\n";

$pdf = membership_card_pdf(792, 480, $content);
header('Content-Type: application/pdf');
header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="HOU-membership-card.pdf"');
echo $pdf;
