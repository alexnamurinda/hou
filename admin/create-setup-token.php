<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../client/config.php';

$pdo = db_connect();
$pdo->beginTransaction();

$credential = $pdo->query(
    'SELECT password_hash FROM admin_credentials WHERE id = 1 FOR UPDATE'
)->fetch();
if (!$credential || $credential['password_hash'] !== null) {
    $pdo->rollBack();
    fwrite(STDERR, "Admin password is already configured; no setup code was created.\n");
    exit(1);
}

$setupCode = bin2hex(random_bytes(32));
$update = $pdo->prepare(
    'UPDATE admin_credentials
     SET setup_token_hash = ?, setup_token_expires_at = DATE_ADD(NOW(), INTERVAL 30 MINUTE)
     WHERE id = 1 AND password_hash IS NULL'
);
$update->execute([hash('sha256', $setupCode)]);
$pdo->commit();

fwrite(STDOUT, "One-time admin setup code (expires in 30 minutes):\n" . $setupCode . "\n");
fwrite(STDOUT, "Open the admin setup page and enter this code. Do not share or save it in the repository.\n");
