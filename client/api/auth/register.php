<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
set_api_headers();
require_method('POST');
start_member_session();

$body = read_json_body();
require_csrf_token($body);

$name = trim((string)($body['name'] ?? ''));
$area = trim((string)($body['area_of_operation'] ?? ''));
$phone = normalize_phone((string)($body['phone'] ?? ''));
$email = strtolower(trim((string)($body['email'] ?? '')));
$password = (string)($body['password'] ?? '');
$passwordConfirmation = (string)($body['password_confirmation'] ?? '');

$rateKey = auth_attempt_key('register', '');
enforce_auth_rate_limit($rateKey, 10, 3600);
record_auth_attempt($rateKey);

if ($name === '' || mb_strlen($name) > 150) {
    respond_json(422, ['success' => false, 'message' => 'Enter your full name (up to 150 characters).']);
}
if ($area === '' || mb_strlen($area) > 180) {
    respond_json(422, ['success' => false, 'message' => 'Enter your area of operation (up to 180 characters).']);
}
if ($phone === null) {
    respond_json(422, ['success' => false, 'message' => 'Enter a valid phone number, including its country code.']);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
    respond_json(422, ['success' => false, 'message' => 'Enter a valid email address.']);
}
if (strlen($password) < 8 || strlen($password) > 4096) {
    respond_json(422, ['success' => false, 'message' => 'Your password must be at least 8 characters.']);
}
if (!hash_equals($password, $passwordConfirmation)) {
    respond_json(422, ['success' => false, 'message' => 'The passwords do not match. Please re-enter the same password.']);
}

$pdo = db_connect();
$existing = $pdo->prepare('SELECT phone, email FROM members WHERE phone = ? OR email = ? LIMIT 1');
$existing->execute([$phone, $email]);
$duplicate = $existing->fetch();
if ($duplicate) {
    $message = $duplicate['phone'] === $phone
        ? 'This phone number is already registered. Try logging in instead.'
        : 'This email address is already registered. Try logging in instead.';
    respond_json(409, ['success' => false, 'message' => $message]);
}

try {
    $insert = $pdo->prepare(
        'INSERT INTO members (full_name, area_of_operation, phone, email, password_hash) VALUES (?, ?, ?, ?, ?)'
    );
    $insert->execute([$name, $area, $phone, $email, password_hash($password, PASSWORD_DEFAULT)]);
} catch (PDOException $error) {
    if ($error->getCode() === '23000') {
        respond_json(409, ['success' => false, 'message' => 'An account already exists with those details. Try logging in instead.']);
    }
    throw $error;
}

session_regenerate_id(true);
$_SESSION['member_id'] = (int)$pdo->lastInsertId();
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

respond_json(201, ['success' => true, 'message' => 'Your HOU member account has been created.']);
