<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
set_api_headers();
require_method('POST');
start_member_session();

$body = read_json_body();
require_csrf_token($body);

$code = trim((string)($body['otp'] ?? ''));
if (!preg_match('/^\d{6}$/', $code)) {
    respond_json(422, ['success' => false, 'message' => 'Enter the six-digit code sent to your email.']);
}

$challenge = $_SESSION['signup_otp'] ?? null;
if (!is_array($challenge) || !isset($challenge['hash'], $challenge['expires_at'], $challenge['attempts'])) {
    respond_json(400, ['success' => false, 'message' => 'Request a new verification code to continue.']);
}
if ((int)$challenge['expires_at'] < time()) {
    unset($_SESSION['signup_otp']);
    respond_json(400, ['success' => false, 'message' => 'Your verification code has expired. Request a new one.']);
}
if ((int)$challenge['attempts'] >= 5) {
    unset($_SESSION['signup_otp']);
    respond_json(429, ['success' => false, 'message' => 'Too many incorrect codes. Request a new verification code.']);
}

if (!password_verify($code, (string)$challenge['hash'])) {
    $_SESSION['signup_otp']['attempts'] = (int)$challenge['attempts'] + 1;
    respond_json(422, ['success' => false, 'message' => 'That code is incorrect. Check the email and try again.']);
}

$_SESSION['signup_verified'] = [
    'name' => $challenge['name'],
    'phone' => $challenge['phone'],
    'email' => $challenge['email'],
    'expires_at' => time() + 900,
];
unset($_SESSION['signup_otp']);
session_regenerate_id(true);

respond_json(200, ['success' => true, 'message' => 'Email verified. You can now create your password.']);
