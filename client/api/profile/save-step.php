<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
set_api_headers();
require_method('POST');
require_csrf_token();
$member = require_member();

function profile_text(string $field, int $maximumLength, bool $required = false): string
{
    $value = $_POST[$field] ?? '';
    if (!is_string($value)) {
        respond_json(422, ['success' => false, 'message' => 'Invalid profile data.']);
    }
    $value = trim($value);
    if (strlen($value) > $maximumLength || ($required && $value === '')) {
        respond_json(422, ['success' => false, 'message' => 'Please check the required fields and try again.']);
    }
    return $value;
}

function profile_upload(string $field, bool $imageOnly): ?array
{
    if (!isset($_FILES[$field])) {
        return null;
    }

    $upload = $_FILES[$field];
    if (
        !is_array($upload)
        || !isset($upload['error'], $upload['tmp_name'], $upload['size'])
        || !is_int($upload['error'])
        || !is_string($upload['tmp_name'])
        || !is_int($upload['size'])
    ) {
        respond_json(422, ['success' => false, 'message' => 'The uploaded document could not be processed.']);
    }
    if ($upload['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($upload['error'] !== UPLOAD_ERR_OK) {
        respond_json(422, ['success' => false, 'message' => 'The document upload failed. Please try again.']);
    }
    if (!is_uploaded_file($upload['tmp_name']) || (int)$upload['size'] > 5 * 1024 * 1024) {
        respond_json(422, ['success' => false, 'message' => 'Documents must be smaller than 5 MB.']);
    }

    $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
    $allowedTypes = $imageOnly
        ? ['image/jpeg' => 'jpg', 'image/png' => 'png']
        : ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
    if (!is_string($mimeType) || !isset($allowedTypes[$mimeType])) {
        respond_json(422, [
            'success' => false,
            'message' => $imageOnly
                ? 'Upload a JPG or PNG image.'
                : 'Upload a PDF, JPG, or PNG document.',
        ]);
    }

    $directory = dirname(__DIR__, 2) . '/private_uploads';
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        error_log('HOU profile upload directory could not be created.');
        respond_json(503, ['success' => false, 'message' => 'Document storage is unavailable. Please try again later.']);
    }

    $filename = bin2hex(random_bytes(24)) . '.' . $allowedTypes[$mimeType];
    $absolutePath = $directory . '/' . $filename;
    error_clear_last();
    $stored = @move_uploaded_file($upload['tmp_name'], $absolutePath);
    $moveError = error_get_last();
    if (!$stored) {
        error_clear_last();
        $stored = @copy($upload['tmp_name'], $absolutePath);
        $copyError = error_get_last();
    } else {
        $copyError = null;
    }

    if (!$stored || !is_file($absolutePath) || filesize($absolutePath) !== (int)$upload['size']) {
        if (is_file($absolutePath) && !unlink($absolutePath)) {
            error_log('HOU incomplete profile upload could not be removed: ' . $absolutePath);
        }
        error_log(
            'HOU profile document could not be saved. Destination writable: '
            . (is_writable($directory) ? 'yes' : 'no')
            . '; move error: ' . ($moveError['message'] ?? 'none')
            . '; copy error: ' . ($copyError['message'] ?? 'none')
        );
        respond_json(503, ['success' => false, 'message' => 'The document could not be saved. Please try again.']);
    }

    return [
        'path' => 'private_uploads/' . $filename,
    ];
}

$step = $_POST['step'] ?? '';
if (!is_string($step)) {
    respond_json(422, ['success' => false, 'message' => 'Invalid onboarding step.']);
}
$pdo = db_connect();
$profileQuery = $pdo->prepare('SELECT business_certificate_path, national_id_photo_path FROM member_profiles WHERE member_id = ?');
$profileQuery->execute([$member['id']]);
$existingProfile = $profileQuery->fetch() ?: [];

switch ($step) {
    case 'address':
        $values = [
            'district' => profile_text('district', 120, true),
            'region' => profile_text('region', 120, true),
            'street_address' => profile_text('street_address', 180, true),
            'postal_address' => profile_text('postal_address', 180, true),
        ];
        break;
    case 'business':
        $values = [
            'business_name' => profile_text('business_name', 180, true),
            'trading_name' => profile_text('trading_name', 180),
            'business_registration_number' => profile_text('business_registration_number', 80),
            'tin' => profile_text('tin', 80),
        ];
        $upload = profile_upload('business_certificate', false);
        if ($upload !== null) {
            $values['business_certificate_path'] = $upload['path'];
        }
        break;
    case 'network':
        $hotspotCount = profile_text('hotspot_count', 10, true);
        $subscriberCount = profile_text('estimated_subscribers', 10, true);
        foreach ([$hotspotCount, $subscriberCount] as $number) {
            if (!preg_match('/^\d{1,10}$/', $number) || (float)$number > 4294967295) {
                respond_json(422, ['success' => false, 'message' => 'Enter valid non-negative numbers for your hotspots and subscribers.']);
            }
        }
        $values = [
            'hotspot_count' => (int)$hotspotCount,
            'estimated_subscribers' => (int)$subscriberCount,
            'operation_areas' => profile_text('operation_areas', 500, true),
            'current_isps' => profile_text('current_isps', 500, true),
        ];
        break;
    case 'citizenship':
        $values = ['national_id_number' => profile_text('national_id_number', 80, true)];
        $upload = profile_upload('national_id_photo', true);
        if ($upload !== null) {
            $values['national_id_photo_path'] = $upload['path'];
        }
        break;
    default:
        respond_json(422, ['success' => false, 'message' => 'Invalid onboarding step.']);
}

$columns = array_keys($values);
$insertColumns = array_merge(['member_id'], $columns);
$placeholders = implode(', ', array_fill(0, count($insertColumns), '?'));
$updates = implode(', ', array_map(
    static fn (string $column): string => $column . ' = VALUES(' . $column . ')',
    $columns
));
$sql = 'INSERT INTO member_profiles (' . implode(', ', $insertColumns) . ') VALUES (' . $placeholders . ') '
    . 'ON DUPLICATE KEY UPDATE ' . $updates;
$statement = $pdo->prepare($sql);
$statement->execute(array_merge([$member['id']], array_values($values)));

if (isset($upload)) {
    $previousPath = $existingProfile[$step === 'business' ? 'business_certificate_path' : 'national_id_photo_path'] ?? null;
    if (is_string($previousPath) && preg_match('~^private_uploads/[a-f0-9]{48}\.(?:pdf|jpg|png)$~', $previousPath)) {
        $previousAbsolutePath = dirname(__DIR__, 2) . '/' . $previousPath;
        if (is_file($previousAbsolutePath) && !unlink($previousAbsolutePath)) {
            error_log('HOU previous profile document could not be removed: ' . $previousPath);
        }
    }
}

respond_json(200, ['success' => true]);
