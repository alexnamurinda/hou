<?php
declare(strict_types=1);

function db_connect(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    // These defaults are for local XAMPP only; configure HOU_DB_* in production.
    $host = getenv('HOU_DB_HOST') ?: '127.0.0.1';
    $port = getenv('HOU_DB_PORT') ?: '3306';
    $name = getenv('HOU_DB_NAME') ?: 'HOU';
    $user = getenv('HOU_DB_USER') ?: 'hou_user';
    $password = getenv('Hou@user321');
    $password = $password === false ? '' : $password;

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);

    try {
        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        ensure_member_tables($pdo);
    } catch (PDOException $error) {
        error_log('HOU database connection or setup failed: ' . $error->getMessage());
        http_response_code(503);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Member services are unavailable. Check the database connection and ensure its user can create tables.',
        ]);
        exit;
    }

    return $pdo;
}

function ensure_member_tables(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS members (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            full_name VARCHAR(150) NOT NULL,
            area_of_operation VARCHAR(180) NOT NULL,
            phone VARCHAR(20) NOT NULL,
            email VARCHAR(254) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            status ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_members_phone (phone),
            UNIQUE KEY uq_members_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS auth_attempts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            attempt_key CHAR(64) NOT NULL,
            attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_auth_attempts_key_time (attempt_key, attempted_at),
            KEY idx_auth_attempts_time (attempted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function start_member_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', (string)(60 * 60 * 24 * 7));
    session_name('HOU_MEMBER');

    $scriptPath = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if (preg_match('~^(.*?)/api(?:/.*)?$~', $scriptPath, $matches)) {
        $clientPath = $matches[1];
    } else {
        $clientPath = dirname($scriptPath);
    }
    $cookiePath = rtrim($clientPath, '/') . '/';
    if ($cookiePath === '//') {
        $cookiePath = '/';
    }

    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 7,
        'path' => $cookiePath,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
