<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/signup-mailer.php';
set_api_headers();
require_method('POST');
start_member_session();

$body = read_json_body();
require_csrf_token($body);

$name = trim((string)($body['name'] ?? ''));
$phone = normalize_phone((string)($body['phone'] ?? ''));
$email = strtolower(trim((string)($body['email'] ?? '')));
if ($name === '' || mb_strlen($name) > 150) {
    respond_json(422, ['success' => false, 'message' => 'Enter your full name (up to 150 characters).']);
}
if ($phone === null) {
    respond_json(422, ['success' => false, 'message' => 'Enter a valid phone number, including its country code.']);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
    respond_json(422, ['success' => false, 'message' => 'Enter a valid email address.']);
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

$rateKey = auth_attempt_key('signup_otp_send', $email);
$ipRateKey = auth_attempt_key('signup_otp_send_ip', '');
enforce_auth_rate_limit($rateKey, 3, 3600);
enforce_auth_rate_limit($ipRateKey, 10, 3600);
$lastAttempt = $pdo->prepare('SELECT attempted_at FROM auth_attempts WHERE attempt_key = ? ORDER BY id DESC LIMIT 1');
$lastAttempt->execute([$rateKey]);
$lastSentAt = $lastAttempt->fetchColumn();
if ($lastSentAt !== false && strtotime((string)$lastSentAt) > time() - 60) {
    respond_json(429, ['success' => false, 'message' => 'Please wait a minute before requesting another code.']);
}
record_auth_attempt($rateKey);
record_auth_attempt($ipRateKey);

$code = (string)random_int(100000, 999999);
try {
    send_signup_otp_email($email, $name, $code);
} catch (Throwable $error) {
    unset($_SESSION['signup_otp'], $_SESSION['signup_verified']);
    error_log('HOU signup verification email failed: ' . $error->getMessage());
    respond_json(503, ['success' => false, 'message' => 'We could not send your verification email right now. Please try again later.']);
}

$_SESSION['signup_otp'] = [
    'name' => $name,
    'phone' => $phone,
    'email' => $email,
    'hash' => password_hash($code, PASSWORD_DEFAULT),
    'expires_at' => time() + 600,
    'attempts' => 0,
];
unset($_SESSION['signup_verified']);

respond_json(200, ['success' => true, 'message' => 'A six-digit verification code has been sent to your email address.']);
