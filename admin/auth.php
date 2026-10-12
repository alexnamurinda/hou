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

function admin_password_hash(): ?string
{
    $hash = getenv('HOU_ADMIN_PASSWORD_HASH');
    return is_string($hash) && $hash !== '' ? $hash : null;
}
