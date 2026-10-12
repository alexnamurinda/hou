<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/signup-mailer.php';
set_api_headers();
require_method('POST');
start_member_session();

$body = read_json_body();
require_csrf_token($body);

$password = (string)($body['password'] ?? '');
$passwordConfirmation = (string)($body['password_confirmation'] ?? '');

$rateKey = auth_attempt_key('register', '');
enforce_auth_rate_limit($rateKey, 10, 3600);
record_auth_attempt($rateKey);

$verified = $_SESSION['signup_verified'] ?? null;
if (
    !is_array($verified)
    || !isset($verified['name'], $verified['phone'], $verified['email'], $verified['expires_at'])
    || (int)$verified['expires_at'] < time()
) {
    unset($_SESSION['signup_verified']);
    respond_json(403, ['success' => false, 'message' => 'Verify your email address before creating your account.']);
}
if (strlen($password) < 8 || strlen($password) > 4096) {
    respond_json(422, ['success' => false, 'message' => 'Your password must be at least 8 characters.']);
}
if (!hash_equals($password, $passwordConfirmation)) {
    respond_json(422, ['success' => false, 'message' => 'The passwords do not match. Please re-enter the same password.']);
}

$name = (string)$verified['name'];
$phone = (string)$verified['phone'];
$email = (string)$verified['email'];
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

$insert = $pdo->prepare(
    'INSERT INTO members (registration_number, full_name, area_of_operation, phone, email, password_hash) VALUES (?, ?, ?, ?, ?, ?)'
);
$pdo->beginTransaction();
$registrationNumber = '';
$memberId = 0;
$inserted = false;
$duplicateMessage = '';
try {
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $registrationNumber = generate_member_registration_number();
        try {
            $insert->execute([
                $registrationNumber,
                $name,
                'Not provided',
                $phone,
                $email,
                password_hash($password, PASSWORD_DEFAULT),
            ]);
            $memberId = (int)$pdo->lastInsertId();
            $inserted = true;
            break;
        } catch (PDOException $error) {
            if ($error->getCode() !== '23000') {
                throw $error;
            }

            $duplicateCheck = $pdo->prepare('SELECT phone, email FROM members WHERE phone = ? OR email = ? LIMIT 1');
            $duplicateCheck->execute([$phone, $email]);
            $duplicate = $duplicateCheck->fetch();
            if ($duplicate) {
                $duplicateMessage = $duplicate['phone'] === $phone
                    ? 'This phone number is already registered. Try logging in instead.'
                    : 'This email address is already registered. Try logging in instead.';
                break;
            }
        }
    }
    if (!$inserted) {
        $pdo->rollBack();
        if ($duplicateMessage !== '') {
            respond_json(409, ['success' => false, 'message' => $duplicateMessage]);
        }
        error_log('HOU registration failed: could not allocate a unique registration number.');
        respond_json(503, ['success' => false, 'message' => 'We could not complete your registration right now. Please try again.']);
    }

    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $error;
}

$emailSent = true;
try {
    send_registration_number_email($email, $name, $registrationNumber);
} catch (Throwable $error) {
    $emailSent = false;
    error_log('HOU registration number email failed: ' . $error->getMessage());
}

unset($_SESSION['signup_verified']);
$_SESSION['member_id'] = $memberId;
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$_SESSION['registration_notice'] = [
    'email_sent' => $emailSent,
];
session_regenerate_id(true);
respond_json(201, [
    'success' => true,
    'registration_number' => $registrationNumber,
    'email_sent' => $emailSent,
    'message' => $emailSent
        ? 'Your account has been created. Your registration number has been emailed to you; keep it safe.'
        : 'Your account was created, but we could not email your registration number. Keep it safe: '
            . $registrationNumber . '.',
]);
