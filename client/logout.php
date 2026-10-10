<?php
declare(strict_types=1);

require_once __DIR__ . '/api/helpers.php';
require_method('POST');
require_csrf_token();

$cookie = session_get_cookie_params();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $cookie['path'],
        'domain' => $cookie['domain'],
        'secure' => $cookie['secure'],
        'httponly' => $cookie['httponly'],
        'samesite' => $cookie['samesite'] ?? 'Lax',
    ]);
}
session_destroy();

header('Location: index.html#login', true, 303);
exit;
