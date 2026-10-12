<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/auth.php';

$setupCode = bin2hex(random_bytes(32));
try {
    $created = admin_credentials_update(
        static function (array &$credentials) use ($setupCode): bool {
            if (!empty($credentials['password_hash'])) {
                return false;
            }

            $credentials['setup_token_hash'] = hash('sha256', $setupCode);
            $credentials['setup_token_expires_at'] = time() + 1800;
            return true;
        },
        true
    );
} catch (Throwable $error) {
    fwrite(STDERR, "Could not create the one-time setup code: " . $error->getMessage() . "\n");
    exit(1);
}
if (!$created) {
    fwrite(STDERR, "Admin password is already configured; no setup code was created.\n");
    exit(1);
}

fwrite(STDOUT, "One-time admin setup code (expires in 30 minutes):\n" . $setupCode . "\n");
fwrite(STDOUT, "Open the admin setup page and enter this code. Do not share or save it in the repository.\n");
