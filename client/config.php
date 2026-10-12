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
    $password = getenv('HOU_DB_PASSWORD');
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
            registration_number CHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            full_name VARCHAR(150) NOT NULL,
            area_of_operation VARCHAR(180) NOT NULL,
            phone VARCHAR(20) NOT NULL,
            email VARCHAR(254) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            status ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_members_registration_number (registration_number),
            UNIQUE KEY uq_members_phone (phone),
            UNIQUE KEY uq_members_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    ensure_member_registration_numbers($pdo);

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

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_credentials (
            id TINYINT UNSIGNED NOT NULL,
            password_hash VARCHAR(255) NULL,
            setup_token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
            setup_token_expires_at TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $pdo->exec('INSERT IGNORE INTO admin_credentials (id) VALUES (1)');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS member_announcements (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(180) NOT NULL,
            body TEXT NOT NULL,
            category ENUM('general', 'event', 'important', 'maintenance') NOT NULL DEFAULT 'general',
            status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
            published_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_member_announcements_visibility (status, published_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS member_profiles (
            member_id BIGINT UNSIGNED NOT NULL,
            district VARCHAR(120) NULL,
            region VARCHAR(120) NULL,
            street_address VARCHAR(180) NULL,
            postal_address VARCHAR(180) NULL,
            business_name VARCHAR(180) NULL,
            trading_name VARCHAR(180) NULL,
            business_registration_number VARCHAR(80) NULL,
            tin VARCHAR(80) NULL,
            business_certificate_path VARCHAR(255) NULL,
            business_certificate_data MEDIUMBLOB NULL,
            business_certificate_mime VARCHAR(100) NULL,
            hotspot_count INT UNSIGNED NULL,
            estimated_subscribers INT UNSIGNED NULL,
            operation_areas VARCHAR(500) NULL,
            current_isps VARCHAR(500) NULL,
            national_id_number VARCHAR(80) NULL,
            national_id_photo_path VARCHAR(255) NULL,
            national_id_photo_data MEDIUMBLOB NULL,
            national_id_photo_mime VARCHAR(100) NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (member_id),
            CONSTRAINT fk_member_profiles_member
                FOREIGN KEY (member_id) REFERENCES members (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    ensure_member_profile_upload_columns($pdo);
}

function ensure_member_profile_upload_columns(PDO $pdo): void
{
    $columns = $pdo->query('SHOW COLUMNS FROM member_profiles')->fetchAll(PDO::FETCH_COLUMN);
    $existingColumns = array_fill_keys($columns, true);
    $uploadColumns = [
        'business_certificate_data' => 'MEDIUMBLOB NULL',
        'business_certificate_mime' => 'VARCHAR(100) NULL',
        'national_id_photo_data' => 'MEDIUMBLOB NULL',
        'national_id_photo_mime' => 'VARCHAR(100) NULL',
    ];

    foreach ($uploadColumns as $column => $definition) {
        if (!isset($existingColumns[$column])) {
            try {
                $pdo->exec('ALTER TABLE member_profiles ADD COLUMN ' . $column . ' ' . $definition);
            } catch (PDOException $error) {
                $columns = $pdo->query('SHOW COLUMNS FROM member_profiles')->fetchAll(PDO::FETCH_COLUMN);
                if (!in_array($column, $columns, true)) {
                    throw $error;
                }
            }
        }
    }
}

function generate_member_registration_number(): string
{
    $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $number = $letters[random_int(0, 25)] . $letters[random_int(0, 25)];
    for ($index = 0; $index < 5; $index++) {
        $number .= (string)random_int(0, 9);
    }
    return $number . $letters[random_int(0, 25)];
}

function ensure_member_registration_numbers(PDO $pdo): void
{
    $column = $pdo->query("SHOW COLUMNS FROM members LIKE 'registration_number'")->fetch();
    if (!$column) {
        try {
            $pdo->exec(
                'ALTER TABLE members ADD COLUMN registration_number CHAR(8) CHARACTER SET ascii COLLATE ascii_bin NULL'
            );
        } catch (PDOException $error) {
            $column = $pdo->query("SHOW COLUMNS FROM members LIKE 'registration_number'")->fetch();
            if (!$column) {
                throw $error;
            }
        }
        $column = $pdo->query("SHOW COLUMNS FROM members LIKE 'registration_number'")->fetch();
    }

    $index = $pdo->query("SHOW INDEX FROM members WHERE Key_name = 'uq_members_registration_number'")->fetch();
    if (!$index) {
        try {
            $pdo->exec('ALTER TABLE members ADD UNIQUE KEY uq_members_registration_number (registration_number)');
        } catch (PDOException $error) {
            $index = $pdo->query("SHOW INDEX FROM members WHERE Key_name = 'uq_members_registration_number'")->fetch();
            if (!$index) {
                throw $error;
            }
        }
    }

    $members = $pdo->query(
        'SELECT id FROM members WHERE registration_number IS NULL ORDER BY id'
    )->fetchAll(PDO::FETCH_COLUMN);
    $update = $pdo->prepare(
        'UPDATE members SET registration_number = ? WHERE id = ? AND registration_number IS NULL'
    );
    foreach ($members as $memberId) {
        do {
            $registrationNumber = generate_member_registration_number();
            try {
                $update->execute([$registrationNumber, $memberId]);
                $assigned = true;
            } catch (PDOException $error) {
                if ($error->getCode() !== '23000') {
                    throw $error;
                }
                $assigned = false;
            }
        } while (!$assigned);
    }

    if ($column['Null'] === 'YES') {
        $pdo->exec('ALTER TABLE members MODIFY registration_number CHAR(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL');
    }
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
