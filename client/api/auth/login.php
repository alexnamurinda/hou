<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
set_api_headers();
require_method('POST');
start_member_session();

$body = read_json_body();
require_csrf_token($body);

$identifier = trim((string)($body['identifier'] ?? $body['phone'] ?? ''));
$registrationNumber = strtoupper($identifier);
$isRegistrationNumber = preg_match('/^[A-Z]{2}\d{5}[A-Z]$/', $registrationNumber) === 1;
$phone = $isRegistrationNumber ? null : normalize_phone($identifier);
$password = (string)($body['password'] ?? '');
$rateIdentity = $isRegistrationNumber ? $registrationNumber : ($phone ?? preg_replace('/\D/', '', $identifier));
$rateKey = auth_attempt_key('login', $rateIdentity);
enforce_auth_rate_limit($rateKey, 10, 900);

$pdo = db_connect();
$member = null;
if (($phone !== null || $isRegistrationNumber) && $password !== '') {
    $query = $pdo->prepare(
        $isRegistrationNumber
            ? 'SELECT id, password_hash, status FROM members WHERE registration_number = ? LIMIT 1'
            : 'SELECT id, password_hash, status FROM members WHERE phone = ? LIMIT 1'
    );
    $query->execute([$isRegistrationNumber ? $registrationNumber : $phone]);
    $member = $query->fetch();
}

if (!$member || !password_verify($password, $member['password_hash'])) {
    record_auth_attempt($rateKey);
    respond_json(401, ['success' => false, 'message' => 'Invalid phone number or registration number, or password.']);
}
if ($member['status'] !== 'active') {
    respond_json(403, ['success' => false, 'message' => 'This account is not active. Please contact HOU.']);
}

session_regenerate_id(true);
$_SESSION['member_id'] = (int)$member['id'];
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

respond_json(200, ['success' => true, 'message' => 'Signed in successfully.']);
