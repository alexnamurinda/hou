<?php
declare(strict_types=1);

function start_admin_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('HOU_ADMIN');

    $scriptPath = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $adminPath = str_replace('\\', '/', dirname($scriptPath));
    $cookiePath = rtrim($adminPath, '/') . '/';
    if ($cookiePath === '//') {
        $cookiePath = '/';
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $cookiePath,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

function admin_csrf_token(): string
{
    start_admin_session();
    if (!isset($_SESSION['admin_csrf_token'])) {
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['admin_csrf_token'];
}

function admin_csrf_is_valid(mixed $provided): bool
{
    start_admin_session();
    $stored = $_SESSION['admin_csrf_token'] ?? '';
    return is_string($provided)
        && is_string($stored)
        && $stored !== ''
        && hash_equals($stored, $provided);
}

function require_admin(): void
{
    start_admin_session();
    if (($_SESSION['hou_admin_authenticated'] ?? false) !== true) {
        header('Location: login.php', true, 303);
        exit;
    }
}

function admin_credentials_file(): string
{
    $configuredPath = getenv('HOU_ADMIN_CREDENTIALS_FILE');
    if (is_string($configuredPath) && $configuredPath !== '') {
        return $configuredPath;
    }

    if (PHP_OS_FAMILY === 'Windows') {
        $programData = getenv('ProgramData');
        $basePath = is_string($programData) && $programData !== '' ? $programData : 'C:\\ProgramData';
        return $basePath . DIRECTORY_SEPARATOR . 'HOU' . DIRECTORY_SEPARATOR . 'admin-credentials.json';
    }

    return '/var/lib/hou/admin-credentials.json';
}

function admin_credentials_lock(bool $createDirectory = false)
{
    $path = admin_credentials_file();
    $directory = dirname($path);
    if (!is_dir($directory) && $createDirectory && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Could not create the protected admin credentials directory.');
    }
    if (!is_dir($directory) || !is_writable($directory)) {
        throw new RuntimeException(
            'The protected admin credentials directory is missing or not writable. '
            . 'Create it outside the website folder and grant access to the PHP web-server user.'
        );
    }

    $lock = fopen($path . '.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX)) {
        if (is_resource($lock)) {
            fclose($lock);
        }
        throw new RuntimeException('Could not lock the protected admin credentials file.');
    }
    if (PHP_OS_FAMILY !== 'Windows' && !chmod($path . '.lock', 0600)) {
        flock($lock, LOCK_UN);
        fclose($lock);
        throw new RuntimeException('Could not secure the admin credentials lock file permissions.');
    }

    return $lock;
}

function admin_credentials_unlock($lock): void
{
    flock($lock, LOCK_UN);
    fclose($lock);
}

function admin_credentials_read_unlocked(): array
{
    $path = admin_credentials_file();
    if (!is_file($path)) {
        return [];
    }

    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException('Could not read the protected admin credentials file.');
    }
    $credentials = json_decode($contents, true, 16, JSON_THROW_ON_ERROR);
    if (!is_array($credentials)) {
        throw new RuntimeException('The protected admin credentials file has an invalid format.');
    }

    return $credentials;
}

function admin_credentials_write_unlocked(array $credentials): void
{
    $path = admin_credentials_file();
    $temporaryPath = $path . '.' . bin2hex(random_bytes(8)) . '.tmp';
    $contents = json_encode($credentials, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    $written = file_put_contents($temporaryPath, $contents, LOCK_EX);
    if ($written !== strlen($contents)) {
        if (is_file($temporaryPath)) {
            unlink($temporaryPath);
        }
        throw new RuntimeException('Could not write the protected admin credentials file.');
    }
    if (PHP_OS_FAMILY !== 'Windows' && !chmod($temporaryPath, 0600)) {
        unlink($temporaryPath);
        throw new RuntimeException('Could not secure the admin credentials file permissions.');
    }
    if (!rename($temporaryPath, $path)) {
        unlink($temporaryPath);
        throw new RuntimeException('Could not replace the protected admin credentials file.');
    }
}

function admin_credentials_update(callable $update, bool $createDirectory = false): mixed
{
    $lock = admin_credentials_lock($createDirectory);
    try {
        $credentials = admin_credentials_read_unlocked();
        $result = $update($credentials);
        admin_credentials_write_unlocked($credentials);
        return $result;
    } finally {
        admin_credentials_unlock($lock);
    }
}

function admin_auth_attempt_count(string $purpose, string $ipAddress, int $windowSeconds): int
{
    $credentials = admin_credentials_read_unlocked();
    $key = hash('sha256', $purpose . '|' . $ipAddress);
    $attempts = $credentials['attempts'][$key] ?? [];
    if (!is_array($attempts)) {
        return 0;
    }

    $cutoff = time() - $windowSeconds;
    return count(array_filter($attempts, static fn($attempt): bool => is_int($attempt) && $attempt >= $cutoff));
}

function record_admin_auth_failure(string $purpose, string $ipAddress): void
{
    admin_credentials_update(static function (array &$credentials) use ($purpose, $ipAddress): void {
        $now = time();
        $cutoff = $now - 86400;
        $attempts = is_array($credentials['attempts'] ?? null) ? $credentials['attempts'] : [];
        foreach ($attempts as $key => $timestamps) {
            if (!is_array($timestamps)) {
                unset($attempts[$key]);
                continue;
            }
            $attempts[$key] = array_values(array_filter(
                $timestamps,
                static fn($timestamp): bool => is_int($timestamp) && $timestamp >= $cutoff
            ));
            if ($attempts[$key] === []) {
                unset($attempts[$key]);
            }
        }

        $key = hash('sha256', $purpose . '|' . $ipAddress);
        $attempts[$key] = is_array($attempts[$key] ?? null) ? $attempts[$key] : [];
        $attempts[$key][] = $now;
        $credentials['attempts'] = $attempts;
    });
}

function admin_password_hash(): ?string
{
    $hash = admin_credentials_read_unlocked()['password_hash'] ?? null;
    return is_string($hash) && $hash !== '' ? $hash : null;
}
