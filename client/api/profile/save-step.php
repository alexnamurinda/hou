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
        ? ['image/jpeg', 'image/png']
        : ['application/pdf', 'image/jpeg', 'image/png'];
    if (!is_string($mimeType) || !in_array($mimeType, $allowedTypes, true)) {
        respond_json(422, [
            'success' => false,
            'message' => $imageOnly
                ? 'Upload a JPG or PNG image.'
                : 'Upload a PDF, JPG, or PNG document.',
        ]);
    }

    $data = file_get_contents($upload['tmp_name']);
    if (!is_string($data) || strlen($data) !== (int)$upload['size']) {
        error_log('HOU profile upload could not be read completely from PHP temporary storage.');
        respond_json(503, ['success' => false, 'message' => 'The document could not be read. Please try again.']);
    }

    return [
        'data' => $data,
        'mime' => $mimeType,
    ];
}

$step = $_POST['step'] ?? '';
if (!is_string($step)) {
    respond_json(422, ['success' => false, 'message' => 'Invalid onboarding step.']);
}
$pdo = db_connect();

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
            $values['business_certificate_data'] = $upload['data'];
            $values['business_certificate_mime'] = $upload['mime'];
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
            $values['national_id_photo_data'] = $upload['data'];
            $values['national_id_photo_mime'] = $upload['mime'];
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
try {
    $statement = $pdo->prepare($sql);
    $statement->bindValue(1, $member['id'], PDO::PARAM_INT);
    foreach ($columns as $index => $column) {
        $value = $values[$column];
        $parameterType = str_ends_with($column, '_data')
            ? PDO::PARAM_LOB
            : (is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        $statement->bindValue($index + 2, $value, $parameterType);
    }
    $statement->execute();
} catch (PDOException $error) {
    error_log('HOU member profile save failed: ' . $error->getMessage());
    respond_json(503, ['success' => false, 'message' => 'We could not save your profile right now. Please try again.']);
}

respond_json(200, ['success' => true]);
